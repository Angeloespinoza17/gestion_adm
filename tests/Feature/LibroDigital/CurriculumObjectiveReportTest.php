<?php

namespace Tests\Feature\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\CurriculumCatalog;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumSource;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\LearningObjectiveSource;
use App\Models\LibroDigital\NormativeSource;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\ReportExport;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\SubjectCurriculumLink;
use App\Models\LibroDigital\TeachingGroup;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\LibroDigital\CurriculumObjectiveIdentity;
use App\Services\LibroDigital\CurriculumObjectiveReportService;
use App\Services\LibroDigital\LibroDigitalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Tests\TestCase;
use ZipArchive;

class CurriculumObjectiveReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_export_seals_a_lightweight_manifest_streams_safe_ooxml_and_allows_owner_polling(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['libro_digital.storage.disk' => 'local', 'libro_digital.storage.root' => 'private/libro-digital']);
        [$school, $year, $profile, $subject, $objectives] = $this->context();
        $user = $this->exportUser($school);

        $response = $this->actingAs($user)->withHeader('Idempotency-Key', 'curriculum-xlsx-0001')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['status' => 'all', 'source' => 'DS-TEST', 'query' => 'Objetivo'],
            ])->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.report_type', 'curriculum_objectives')
            ->assertJsonPath('data.format', 'xlsx');

        $export = ReportExport::query()->where('public_id', $response->json('data.public_id'))->firstOrFail();
        $snapshotJson = Crypt::decryptString(Storage::disk('local')->get($export->source_private_path));
        $snapshot = json_decode($snapshotJson, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $snapshot['curriculum_manifest']['objective_count']);
        $this->assertSame(2, $snapshot['curriculum_manifest']['relationship_count']);
        $this->assertSame([], $snapshot['sections']);
        $this->assertLessThan(20000, strlen($snapshotJson));
        $this->assertStringNotContainsString('private_path', $snapshotJson);
        $this->assertSame(hash('sha256', $snapshotJson), $export->source_snapshot_hash);

        $this->getJson('/api/libro-digital/v1/reports/'.$export->public_id)
            ->assertOk()->assertJsonPath('data.public_id', $export->public_id);

        app(LibroDigitalReportService::class)->generate($export->fresh());
        $completed = $export->fresh();
        $this->assertSame('completed', $completed->status->value);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($completed->private_path)), $completed->sha256);

        $path = tempnam(sys_get_temp_dir(), 'curriculum-report-test-');
        file_put_contents($path, Storage::disk('local')->get($completed->private_path));
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path) === true);
            $objectivesXml = $zip->getFromName('xl/worksheets/sheet2.xml');
            $sourcesXml = $zip->getFromName('xl/worksheets/sheet3.xml');
            $styles = $zip->getFromName('xl/styles.xml');
            $this->assertIsString($objectivesXml);
            $this->assertIsString($sourcesXml);
            $this->assertStringContainsString('<cols>', $objectivesXml);
            $this->assertStringContainsString('autoFilter ref="A1:X3"', $objectivesXml);
            $this->assertStringContainsString('Objetivo activo', $objectivesXml);
            $this->assertStringContainsString("&apos;\t=2+3 Objetivo inactivo", $objectivesXml);
            $this->assertStringContainsString('ht="', $objectivesXml);
            $this->assertStringContainsString('DS-TEST', $sourcesXml);
            $this->assertStringContainsString('wrapText="1"', (string) $styles);
            $this->assertStringNotContainsString('<f', $objectivesXml.$sourcesXml);
            $this->assertStringNotContainsString('private/', $objectivesXml.$sourcesXml);
        } finally {
            $zip->close();
            @unlink($path);
        }

        $download = $this->get('/api/libro-digital/v1/reports/'.$completed->public_id.'/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertSame(Storage::disk('local')->get($completed->private_path), $download->streamedContent());
        $this->assertDatabaseHas('lcd_audit_events', [
            'school_id' => $school->id,
            'event' => 'lcd.report.downloaded',
            'actor_user_id' => $user->id,
        ]);

        $otherExporter = $this->exportUser($school);
        $this->actingAs($otherExporter)->getJson('/api/libro-digital/v1/reports/'.$completed->public_id)->assertForbidden();
        $this->get('/api/libro-digital/v1/reports/'.$completed->public_id.'/download')->assertForbidden();
    }

    public function test_pdf_uses_complete_curriculum_text_and_rejects_unbounded_exports_before_creating_a_report(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['libro_digital.storage.disk' => 'local']);
        [$school, $year, , , $objectives] = $this->context(longDescription: true);
        $user = $this->exportUser($school);

        $response = $this->actingAs($user)->withHeader('Idempotency-Key', 'curriculum-pdf-0001')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'report_type' => 'curriculum_objectives',
                'format' => 'pdf',
                'filters' => ['status' => 'active'],
            ])->assertAccepted();
        $export = ReportExport::query()->where('public_id', $response->json('data.public_id'))->firstOrFail();
        app(LibroDigitalReportService::class)->generate($export->fresh());
        $pdf = Storage::disk('local')->get($export->fresh()->private_path);
        $text = preg_replace('/\s+/u', ' ', (new Parser)->parseContent($pdf)->getText());
        $this->assertStringContainsString('INICIO-TEXTO-OFICIAL', $text);
        $this->assertStringContainsString('FIN-TEXTO-OFICIAL', $text);
        $this->assertStringNotContainsString('FIN-TEXTO-OF…', $text);
        $this->assertStringContainsString('café ϵ ≠', $text);

        $rows = [];
        for ($index = 3; $index <= 750; $index++) {
            $code = 'OA-5B-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
            $rows[] = $this->objectivePayload($objectives[0]->curriculum_catalog_id, $objectives[0]->schedule_subject_id, $code, 'Objetivo '.$index, true);
        }
        LearningObjective::query()->insert($rows);

        $this->actingAs($user)->withHeader('Idempotency-Key', 'curriculum-pdf-limit-accepted-0750')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'report_type' => 'curriculum_objectives',
                'format' => 'pdf',
                'filters' => ['status' => 'all'],
            ])->assertAccepted();

        LearningObjective::query()->create($this->objectivePayload(
            $objectives[0]->curriculum_catalog_id,
            $objectives[0]->schedule_subject_id,
            'OA-5B-0751',
            'Objetivo 751',
            true,
        ));
        $before = ReportExport::query()->count();

        $this->withHeader('Idempotency-Key', 'curriculum-pdf-limit-0001')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'report_type' => 'curriculum_objectives',
                'format' => 'pdf',
                'filters' => ['status' => 'all'],
            ])->assertUnprocessable()
            ->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_PDF_LIMIT_EXCEEDED')
            ->assertJsonPath('details.0.maximum', 750)
            ->assertJsonPath('details.0.alternative_format', 'xlsx');
        $this->assertSame($before, ReportExport::query()->count());
    }

    public function test_course_scope_derives_grade_fail_closed_and_book_export_rejects_inactive_status(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$school, $year, $profile] = $this->context();
        $user = $this->exportUser($school);
        $level = EducationLevel::query()->create([
            'name' => 'Nivel Transición 1',
            'order' => ((int) EducationLevel::query()->max('order')) + 1,
            'type' => 'parvularia',
        ]);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => 'NT1 A',
            'capacity' => 30,
            'active' => true,
        ]);
        $book = $this->book($school, $year, $profile, $course);

        $this->actingAs($user)->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'status' => 'all',
        ]))->assertOk()
            ->assertJsonPath('meta.summary.filtered_objectives', 0)
            ->assertJsonPath('meta.export_limits.filtered_objectives', 0);

        $this->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'grade_code' => '5B',
        ]))->assertUnprocessable()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_COURSE_FILTER_CONFLICT');

        $response = $this->actingAs($user)->withHeader('Idempotency-Key', 'curriculum-course-0001')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['course_section_id' => $course->id],
            ])->assertAccepted()
            ->assertJsonPath('data.filters.grade_code', 'NT1')
            ->assertJsonPath('data.filters.level_code', 'PARVULARIA');
        $this->assertSame(0, $this->manifest($response->json('data.public_id'))['objective_count']);

        $this->withHeader('Idempotency-Key', 'curriculum-course-conflict-0001')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['course_section_id' => $course->id, 'grade_code' => '5B'],
            ])->assertUnprocessable()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_COURSE_FILTER_CONFLICT');

        $this->withHeader('Idempotency-Key', 'curriculum-book-status-0001')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'book_id' => $book->public_id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['status' => 'inactive'],
            ])->assertUnprocessable()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_BOOK_STATUS_INVALID');

        $foreignSchool = School::query()->create(['rbd' => '99999-9', 'name' => 'Otro', 'timezone' => 'America/Santiago', 'active' => true]);
        FeatureFlag::query()->create(['school_id' => $foreignSchool->id, 'scope_key' => 'school:'.$foreignSchool->id, 'code' => 'lcd_enabled', 'enabled' => true]);
        $foreignSchool->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id,
            'rbd_snapshot' => $foreignSchool->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $foreignSchool->timezone,
            'active' => true,
        ]);
        $foreignCourse = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'B',
            'display_name' => 'NT1 B',
            'capacity' => 30,
            'active' => true,
        ]);
        $this->book($foreignSchool, $year, $profile, $foreignCourse);

        $this->withHeader('Idempotency-Key', 'curriculum-course-foreign-0001')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['course_section_id' => $foreignCourse->id],
            ])->assertForbidden()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_COURSE_SCOPE_INVALID');

        $this->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $foreignCourse->id,
        ]))->assertForbidden()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_COURSE_SCOPE_INVALID');
    }

    public function test_generation_fails_with_actionable_stale_code_when_the_sealed_corpus_changes(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['libro_digital.storage.disk' => 'local']);
        [$school, $year, , , $objectives] = $this->context();
        $user = $this->exportUser($school);
        $response = $this->actingAs($user)->withHeader('Idempotency-Key', 'curriculum-stale-0001')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['status' => 'all'],
            ])->assertAccepted();
        $export = ReportExport::query()->where('public_id', $response->json('data.public_id'))->firstOrFail();
        $objectives[0]->forceFill(['description' => 'Contenido alterado después del sello'])->save();

        try {
            app(LibroDigitalReportService::class)->generate($export->fresh());
            $this->fail('La generación debió detectar la instantánea obsoleta.');
        } catch (LibroDigitalException $exception) {
            $this->assertSame('LCD_CURRICULUM_EXPORT_SNAPSHOT_STALE', $exception->errorCode);
        }
        $this->assertSame('failed', $export->fresh()->status->value);
        $this->assertSame('LCD_CURRICULUM_EXPORT_SNAPSHOT_STALE', $export->fresh()->failure_code);
        $this->assertNull($export->fresh()->private_path);
    }

    public function test_report_storage_fails_closed_before_creating_rows_when_disk_is_public(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$school, $year] = $this->context();
        $user = $this->exportUser($school);
        config(['libro_digital.storage.disk' => 'public']);
        $before = ReportExport::query()->count();

        $this->actingAs($user)->withHeader('Idempotency-Key', 'curriculum-public-disk-rejected')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['status' => 'all'],
            ])->assertStatus(503)->assertJsonPath('code', 'LCD_REPORT_STORAGE_NOT_PRIVATE');

        $this->assertSame($before, ReportExport::query()->count());
    }

    public function test_book_list_and_export_intersect_eligible_catalogs_and_ignore_inactive_groups(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$school, $year, $profile, $subject, $objectives] = $this->context();
        $user = $this->exportUser($school);
        $level = EducationLevel::query()->create([
            'name' => 'Nivel Transición 1',
            'order' => ((int) EducationLevel::query()->max('order')) + 1,
            'type' => 'parvularia',
        ]);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => 'NT1 A',
            'capacity' => 30,
            'active' => true,
        ]);
        $book = $this->book($school, $year, $profile, $course);
        TeachingGroup::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'book_id' => $book->id,
            'course_section_id' => $course->id,
            'schedule_subject_id' => $subject->id,
            'code' => 'NT1-LEN-ACTIVE',
            'name' => 'NT1 A · Lenguaje',
            'course_snapshot' => 'NT1 A',
            'subject_snapshot' => $subject->name,
            'valid_from' => $year->starts_at,
            'valid_to' => $year->ends_at,
            'status' => 'active',
        ]);

        $normative = NormativeSource::query()->create([
            'title' => 'Lote no activado',
            'sha256' => hash('sha256', 'bad-batch-source'),
            'private_path' => 'private/bad-batch.enc',
            'status' => 'verified_metadata_only',
        ]);
        $ineligibleCatalog = CurriculumCatalog::query()->create([
            'normative_source_id' => $normative->id,
            'code' => 'CAT-INELIGIBLE',
            'name' => 'Catálogo con lote no activado',
            'version' => '1',
            'source_hash' => 'not-a-valid-sha256',
            'active' => true,
        ]);
        $ineligibleBatch = CurriculumImportBatch::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $ineligibleCatalog->id,
            'normative_source_id' => $normative->id,
            'idempotency_key' => hash('sha256', 'ineligible-batch'),
            'status' => CurriculumImportBatch::STATUS_VALIDATED,
            'catalog_code' => $ineligibleCatalog->code,
            'catalog_version' => $ineligibleCatalog->version,
            'original_name' => 'ineligible.xlsx',
            'private_path' => 'private/ineligible.enc',
            'source_hash' => hash('sha256', 'bad-batch-source'),
            'manifest_hash' => hash('sha256', 'ineligible-manifest'),
            'objective_count' => 1,
            'requested_at' => now('UTC'),
        ]);
        CurriculumCatalogActivation::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $ineligibleCatalog->id,
            'import_batch_id' => $ineligibleBatch->id,
            'activation_version' => 1,
            'idempotency_key' => hash('sha256', 'ineligible-activation'),
            'status' => CurriculumCatalogActivation::STATUS_ACTIVATED,
            'scope_snapshot' => ['invalid_batch_state' => true],
            'requested_at' => now('UTC'),
            'activated_at' => now('UTC'),
        ]);
        SubjectCurriculumLink::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'schedule_subject_id' => $subject->id,
            'curriculum_catalog_id' => $ineligibleCatalog->id,
            'level_code' => 'PARVULARIA',
            'grade_code' => 'NT1',
            'valid_from' => $year->starts_at,
            'valid_to' => $year->ends_at,
            'active' => true,
        ]);
        LearningObjective::query()->create([
            ...$this->objectivePayload($ineligibleCatalog->id, $subject->id, 'OA-NT1-01', 'No debe exportarse', true),
            'level_code' => 'PARVULARIA',
            'grade_code' => 'NT1',
        ]);
        SubjectCurriculumLink::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'schedule_subject_id' => $subject->id,
            'curriculum_catalog_id' => $objectives[0]->curriculum_catalog_id,
            'level_code' => 'PARVULARIA',
            'grade_code' => 'NT1',
            'valid_from' => $year->starts_at,
            'valid_to' => $year->ends_at,
            'active' => true,
        ]);
        $eligibleObjective = LearningObjective::query()->create([
            ...$this->objectivePayload(
                $objectives[0]->curriculum_catalog_id,
                $subject->id,
                'OA-NT1-ELIGIBLE',
                'Objetivo elegible del libro',
                true,
            ),
            'level_code' => 'PARVULARIA',
            'grade_code' => 'NT1',
        ]);
        $before = ReportExport::query()->count();

        $list = $this->actingAs($user)->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'book_id' => $book->public_id,
            'schedule_subject_id' => $subject->id,
            'status' => 'active',
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $eligibleObjective->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.summary.total_objectives', 1)
            ->assertJsonPath('meta.summary.filtered_objectives', 1)
            ->assertJsonPath('meta.export_limits.filtered_objectives', 1)
            ->assertJsonPath('meta.facets.statuses.0.count', 1)
            ->assertJsonPath('meta.facets.statuses.1.count', 0)
            ->assertJsonPath('meta.facets_scope', 'book_active_scope');
        $this->assertStringNotContainsString('No debe exportarse', json_encode($list->json(), JSON_THROW_ON_ERROR));

        $exportResponse = $this->withHeader('Idempotency-Key', 'curriculum-ineligible-book-catalog')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'book_id' => $book->public_id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['status' => 'active', 'schedule_subject_id' => $subject->id],
            ])->assertAccepted();
        $this->assertSame($before + 1, ReportExport::query()->count());
        $this->assertSame(1, $this->manifest($exportResponse->json('data.public_id'))['objective_count']);

        $inactiveSubject = ScheduleSubject::query()->create([
            'name' => 'Asignatura histórica', 'code' => 'HIST', 'area' => 'Otra', 'color' => '#999999', 'active' => true,
        ]);
        TeachingGroup::query()->create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'book_id' => $book->id,
            'course_section_id' => $course->id, 'schedule_subject_id' => $inactiveSubject->id,
            'code' => 'NT1-HIST-INACTIVE', 'name' => 'Grupo histórico', 'course_snapshot' => 'NT1 A',
            'subject_snapshot' => $inactiveSubject->name, 'valid_from' => $year->starts_at,
            'valid_to' => $year->ends_at, 'status' => 'inactive',
        ]);
        $this->withHeader('Idempotency-Key', 'curriculum-inactive-book-group')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'book_id' => $book->public_id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['status' => 'active', 'subject_code' => 'HIST'],
            ])->assertUnprocessable()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_BOOK_SUBJECT_INVALID');
        $this->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'book_id' => $book->public_id,
            'schedule_subject_id' => $inactiveSubject->id,
            'status' => 'active',
        ]))->assertUnprocessable()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_BOOK_SUBJECT_INVALID');
        $this->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'book_id' => $book->public_id,
            'schedule_subject_id' => $subject->id,
            'status' => 'all',
        ]))->assertUnprocessable()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_BOOK_STATUS_INVALID');

        $book->forceFill(['status' => 'archived'])->save();
        $this->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'book_id' => $book->public_id,
            'schedule_subject_id' => $subject->id,
            'status' => 'active',
        ]))->assertUnprocessable()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_BOOK_ARCHIVED');
        $this->withHeader('Idempotency-Key', 'curriculum-archived-book')
            ->postJson('/api/libro-digital/v1/reports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'book_id' => $book->public_id,
                'report_type' => 'curriculum_objectives',
                'format' => 'xlsx',
                'filters' => ['status' => 'active'],
            ])->assertUnprocessable()->assertJsonPath('code', 'LCD_CURRICULUM_EXPORT_BOOK_ARCHIVED');

        config(['libro_digital.reports.curriculum_max_rows' => 20000]);
        $this->assertSame(10000, CurriculumObjectiveReportService::maximumRows());
    }

    /** @return array{School,AcademicYear,RegulatoryProfile,ScheduleSubject,array{LearningObjective,LearningObjective}} */
    private function context(bool $longDescription = false): array
    {
        $school = School::query()->create(['rbd' => '12345-6', 'name' => 'Escuela Prueba', 'timezone' => 'America/Santiago', 'active' => true]);
        $year = AcademicYear::factory()->create(['year' => 2035, 'name' => '2035', 'starts_at' => '2035-03-01', 'ends_at' => '2035-12-20', 'is_active' => true]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-CURR', 'name' => 'Perfil curricular', 'version' => '1', 'effective_from' => '2030-01-01',
            'retention_years' => 6, 'rules_snapshot' => [], 'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id, 'rbd_snapshot' => $school->rbd, 'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone, 'active' => true,
        ]);
        FeatureFlag::query()->create(['school_id' => $school->id, 'scope_key' => 'school:'.$school->id, 'code' => 'lcd_enabled', 'enabled' => true]);
        $subject = ScheduleSubject::query()->create(['name' => 'Lenguaje', 'code' => 'LEN', 'area' => 'Lenguaje', 'color' => '#405189', 'active' => true]);

        $workbookHash = hash('sha256', 'workbook');
        $catalogHash = hash('sha256', 'catalog');
        $officialHash = hash('sha256', 'official');
        $workbookSource = NormativeSource::query()->create(['title' => 'Workbook', 'sha256' => $workbookHash, 'private_path' => 'private/workbook.enc', 'status' => 'verified']);
        $catalog = CurriculumCatalog::query()->create([
            'normative_source_id' => $workbookSource->id, 'code' => 'CAT-TEST', 'name' => 'Currículum oficial',
            'version' => '2026.1', 'authority' => 'MINEDUC', 'source_url' => 'https://www.curriculumnacional.cl/',
            'source_hash' => $catalogHash, 'active' => true,
        ]);
        $batch = CurriculumImportBatch::query()->create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'curriculum_catalog_id' => $catalog->id,
            'normative_source_id' => $workbookSource->id, 'idempotency_key' => hash('sha256', 'batch'),
            'status' => CurriculumImportBatch::STATUS_ACTIVATED, 'catalog_code' => $catalog->code, 'catalog_version' => $catalog->version,
            'original_name' => 'curriculum.xlsx', 'private_path' => 'private/curriculum.enc', 'source_hash' => $workbookHash,
            'manifest_hash' => hash('sha256', 'manifest'), 'objective_count' => 2, 'requested_at' => now('UTC'), 'completed_at' => now('UTC'),
        ]);
        CurriculumCatalogActivation::query()->create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'curriculum_catalog_id' => $catalog->id,
            'import_batch_id' => $batch->id, 'activation_version' => 1, 'idempotency_key' => hash('sha256', 'activation'),
            'status' => CurriculumCatalogActivation::STATUS_ACTIVATED, 'scope_snapshot' => ['complete' => true],
            'decision_hash' => hash('sha256', 'decision'), 'requested_at' => now('UTC'), 'activated_at' => now('UTC'),
        ]);
        $normative = NormativeSource::query()->create([
            'title' => 'Bases oficiales', 'authority' => 'MINEDUC', 'document_number' => 'DS-TEST',
            'source_url' => 'https://www.curriculumnacional.cl/bases.pdf', 'sha256' => $officialHash,
            'private_path' => 'private/official.pdf.enc', 'status' => 'verified', 'metadata' => ['private_path' => 'never-leak'],
        ]);
        $source = CurriculumSource::query()->create([
            'curriculum_catalog_id' => $catalog->id, 'normative_source_id' => $normative->id, 'source_key' => 'DS-TEST',
            'source_scope' => 'GENERAL', 'source_name' => 'Bases oficiales', 'authority' => 'MINEDUC',
            'document_number' => 'DS-TEST', 'source_url' => 'https://www.curriculumnacional.cl/bases.pdf',
            'declared_sha256' => $officialHash, 'verified_sha256' => $officialHash, 'status' => 'verified',
            'metadata' => ['private_path' => 'never-leak'],
        ]);
        $description = $longDescription
            ? 'INICIO-TEXTO-OFICIAL caf'."e\u{301}".' ϵ ≠ '.str_repeat('desarrollo curricular completo y verificable ', 55).' FIN-TEXTO-OFICIAL'
            : 'Objetivo activo';
        $active = LearningObjective::query()->create($this->objectivePayload($catalog->id, $subject->id, 'OA-5B-01', $description, true));
        $inactive = LearningObjective::query()->create($this->objectivePayload($catalog->id, $subject->id, 'OA-5B-02', "\t=2+3 Objetivo inactivo", false));
        foreach ([$active, $inactive] as $objective) {
            LearningObjectiveSource::query()->create([
                'learning_objective_id' => $objective->id, 'curriculum_source_id' => $source->id,
                'source_role' => 'canonical_text', 'source_locator' => $objective->source_page,
                'relationship_hash' => hash('sha256', 'relationship-'.$objective->id),
                'source_snapshot' => ['source_key' => 'DS-TEST', 'private_path' => 'never-leak'],
            ]);
        }

        return [$school, $year, $profile, $subject, [$active, $inactive]];
    }

    /** @return array<string, mixed> */
    private function objectivePayload(int $catalogId, int $subjectId, string $code, string $description, bool $active): array
    {
        return [
            'public_id' => (string) Str::ulid(), 'curriculum_catalog_id' => $catalogId, 'schedule_subject_id' => $subjectId,
            'level_code' => 'BASICA', 'grade_code' => '5B', 'curriculum_track' => null, 'axis_code' => 'LECTURA',
            'objective_type' => 'OA', 'code' => $code, 'objective_key' => CurriculumObjectiveIdentity::key([
                'code' => $code, 'objective_type' => 'OA', 'subject_code' => 'LEN', 'level_code' => 'BASICA',
                'grade_code' => '5B', 'curriculum_track' => null, 'axis_code' => 'LECTURA',
            ]),
            'description' => $description, 'source_page' => 'p. 42', 'active' => $active,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function exportUser(School $school): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create(['name' => 'Exportador curricular', 'slug' => 'exportador-curricular-'.Str::lower(Str::random(6)), 'active' => true]);
        $permissions = collect(['libro_digital.access', 'libro_digital.books.view', 'libro_digital.reports.export'])->map(fn (string $slug): int => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'active' => true],
        )->id);
        $role->permissions()->sync($permissions);
        $user->roles()->sync([$role->id]);
        $school->users()->attach($user->id, ['active' => true]);

        return $user;
    }

    private function book(School $school, AcademicYear $year, RegulatoryProfile $profile, CourseSection $course): Book
    {
        return Book::query()->create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'regulatory_profile_id' => $profile->id,
            'course_section_id' => $course->id, 'code' => 'BOOK-'.$school->id.'-'.$course->id,
            'rbd_snapshot' => $school->rbd, 'year_snapshot' => $year->year, 'level_code' => 'PARVULARIA',
            'grade_code' => 'NT1', 'course_label' => $course->display_name, 'status' => 'draft', 'source_format' => 'native',
        ]);
    }

    /** @return array<string, mixed> */
    private function manifest(string $publicId): array
    {
        $export = ReportExport::query()->where('public_id', $publicId)->firstOrFail();
        $snapshot = json_decode(Crypt::decryptString(Storage::disk('local')->get($export->source_private_path)), true, flags: JSON_THROW_ON_ERROR);

        return $snapshot['curriculum_manifest'];
    }
}
