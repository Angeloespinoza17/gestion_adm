<?php

namespace Tests\Feature\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\LibroDigital\BookProvisioningService;
use App\Services\LibroDigital\CompliancePreflightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibroDigitalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_book_seals_a_roster_without_modifying_enrollments(): void
    {
        [$user, $school, $year, $course, $subject, $teacher, $profile] = $this->context();
        $student = StudentProfile::factory()->create(['registered_name' => 'Estudiante Prueba']);
        $enrollment = StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'matriculada',
            'enrolled_at' => $year->starts_at,
            'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $course->educationLevel->name,
            'snapshot_section_name' => $course->section_name,
            'snapshot_course_display_name' => $course->display_name,
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'Idempotency-Key' => 'lcd-book-create-0001',
            'X-Correlation-ID' => 'lcd-test-book-create',
        ])->postJson('/api/libro-digital/v1/books', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'schedule_subject_id' => $subject->id,
            'teacher_staff_id' => $teacher->id,
            'normative_profile_id' => $profile->id,
            'name' => 'Libro de Lenguaje',
            'modality' => 'regular',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.roster_count', 1)
            ->assertJsonPath('data.lock_version', 1);
        $book = Book::query()->firstOrFail();
        $this->assertDatabaseHas('lcd_enrollment_links', ['book_id' => $book->id, 'student_enrollment_id' => $enrollment->id]);
        $this->assertDatabaseHas('lcd_roster_snapshots', ['book_id' => $book->id, 'status' => 'sealed', 'student_count' => 1]);
        $this->assertDatabaseHas('lcd_roster_snapshot_items', ['student_profile_id' => $student->id, 'student_enrollment_id' => $enrollment->id]);
        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollment->id, 'enrollment_status' => 'matriculada']);
        $this->getJson('/api/libro-digital/v1/books/'.$book->id.'/roster?date=2035-03-01')
            ->assertOk()->assertJsonPath('data.0.student_profile_id', $student->id)
            ->assertJsonPath('meta.snapshot.status', 'sealed');
    }

    public function test_stale_book_update_is_rejected_with_precondition_failed(): void
    {
        [$user, $school, $year, $course, $subject, $teacher, $profile] = $this->context();
        $created = $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-book-create-0002')->postJson('/api/libro-digital/v1/books', [
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'course_section_id' => $course->id,
            'schedule_subject_id' => $subject->id, 'teacher_staff_id' => $teacher->id,
            'normative_profile_id' => $profile->id, 'name' => 'Libro', 'modality' => 'regular',
        ])->assertCreated();

        $this->withHeaders(['Idempotency-Key' => 'lcd-book-update-0002', 'If-Match' => '999'])
            ->patchJson('/api/libro-digital/v1/books/'.$created->json('data.id'), ['name' => 'Cambio obsoleto', 'lock_version' => 999])
            ->assertStatus(412)->assertJsonPath('code', 'LCD_VERSION_CONFLICT');
    }

    public function test_school_context_cannot_cross_an_active_membership(): void
    {
        [, $school] = $this->context();
        $other = School::query()->create(['rbd' => '99999-9', 'name' => 'Otro establecimiento', 'timezone' => 'America/Santiago', 'active' => true]);
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create(['name' => 'Consulta LCD', 'slug' => 'consulta-lcd', 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'libro_digital.access'],
            ['name' => 'Acceso LCD', 'active' => true],
        );
        $role->permissions()->sync([$permission->id]);
        $user->roles()->sync([$role->id]);
        $school->users()->attach($user->id, ['active' => true]);

        $this->actingAs($user)->getJson('/api/libro-digital/v1/catalogs?school_id='.$other->id)->assertForbidden();
    }

    public function test_report_requests_accept_all_supported_formats(): void
    {
        Queue::fake();
        [$user, $school] = $this->context();

        foreach (['pdf', 'xlsx', 'csv', 'json'] as $format) {
            $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-report-'.$format.'-0001')
                ->postJson('/api/libro-digital/v1/reports', [
                    'school_id' => $school->id,
                    'report_type' => 'official_roster',
                    'format' => $format,
                    'period' => 'academic_year',
                ])->assertAccepted()->assertJsonPath('data.format', $format)->assertJsonPath('data.status', 'queued');
        }

        $this->assertDatabaseCount('lcd_report_exports', 4);
    }

    public function test_sensitive_feature_flags_fail_closed_when_capability_is_not_ready(): void
    {
        [$user, $school] = $this->context();

        $this->actingAs($user)->withHeaders(['Idempotency-Key' => 'lcd-config-sensitive-0001', 'If-Match' => '1'])
            ->putJson('/api/libro-digital/v1/configuration', [
                'school_id' => $school->id,
                'timezone' => 'America/Santiago',
                'attendance_autosave_seconds' => 10,
                'feature_flags' => [
                    'lcd_enabled' => false,
                    'parvularia_enabled' => false,
                    'identity_features_enabled' => false,
                    'ede_exports_enabled' => false,
                    'sige_integration_enabled' => true,
                    'fiscalization_mode_enabled' => false,
                ],
                'lock_version' => 1,
            ])->assertUnprocessable()->assertJsonPath('code', 'LCD_CONFIGURATION_CAPABILITY_NOT_READY');

        $this->assertDatabaseMissing('lcd_feature_flags', ['school_id' => $school->id, 'code' => 'lcd_sige_reconciliation_enabled', 'enabled' => true]);
    }

    public function test_subject_api_rejects_fields_the_global_catalog_cannot_persist(): void
    {
        [$user, $school] = $this->context();

        $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-subject-unsupported-0001')
            ->postJson('/api/libro-digital/v1/subjects', [
                'school_id' => $school->id,
                'name' => 'Asignatura no persistible',
                'type' => 'common',
            ])->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->assertDatabaseMissing('schedule_subjects', ['name' => 'Asignatura no persistible']);
    }

    public function test_subject_profiles_external_aliases_and_bulk_status_are_additive_and_audited(): void
    {
        [$user, $school, , , $subject] = $this->context();
        $version = $subject->updated_at?->getTimestamp() ?: 1;

        $this->actingAs($user)->withHeaders([
            'Idempotency-Key' => 'lcd-subject-profile-0001',
            'If-Match' => (string) $version,
        ])->patchJson('/api/libro-digital/v1/subjects/'.$subject->id, [
            'school_id' => $school->id,
            'display_name' => 'Lengua y Literatura',
            'subject_type' => 'common_plan',
            'description' => 'Nombre visible institucional sin alterar la identidad técnica.',
            'education_types' => ['basica', 'media'],
            'lock_version' => $version,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Lengua y Literatura')
            ->assertJsonPath('data.technical_name', 'Lenguaje')
            ->assertJsonPath('data.education_types.0', 'basica');

        $catalog = $this->getJson('/api/libro-digital/v1/subjects/external-catalog?school_id='.$school->id.'&source_system=legacy_gradebook')
            ->assertOk();
        $external = collect($catalog->json('data'))->first(
            fn (array $item): bool => $item['scope_code'] === 'basica' && $item['external_name'] === 'Lenguaje y Comunicación'
        );
        $this->assertNotNull($external);

        $this->withHeader('Idempotency-Key', 'lcd-subject-alias-0001')
            ->putJson('/api/libro-digital/v1/subjects/external-mappings', [
                'school_id' => $school->id,
                'source_system' => 'legacy_gradebook',
                'mappings' => [[
                    'scope_code' => 'basica',
                    'external_name' => 'Lenguaje y Comunicación',
                    'schedule_subject_id' => $subject->id,
                ]],
            ])->assertOk()->assertJsonPath('meta.mapped', 1);

        $this->assertDatabaseHas('lcd_subject_external_aliases', [
            'school_id' => $school->id,
            'schedule_subject_id' => $subject->id,
            'source_system' => 'legacy_gradebook',
            'scope_code' => 'basica',
            'normalized_name' => 'lenguaje y comunicacion',
            'active' => true,
        ]);

        $this->withHeader('Idempotency-Key', 'lcd-subject-bulk-status-0001')
            ->postJson('/api/libro-digital/v1/subjects/bulk-status', [
                'school_id' => $school->id,
                'subject_ids' => [$subject->id],
                'active' => false,
            ])->assertOk()->assertJsonPath('meta.changed', 1);
        $this->assertDatabaseHas('schedule_subjects', ['id' => $subject->id, 'active' => false]);
        $this->assertDatabaseHas('lcd_audit_events', [
            'school_id' => $school->id,
            'event' => 'lcd.subject.external_mappings_confirmed',
        ]);
        $this->assertDatabaseHas('lcd_audit_events', [
            'school_id' => $school->id,
            'event' => 'lcd.subject.bulk_status_changed',
        ]);
    }

    public function test_bulk_book_activation_runs_preflight_and_preserves_each_transition(): void
    {
        [$user, $school, $year, $course, $subject, $teacher, $profile] = $this->context();
        $created = $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-book-bulk-create-0001')
            ->postJson('/api/libro-digital/v1/books', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'course_section_id' => $course->id,
                'schedule_subject_id' => $subject->id,
                'teacher_staff_id' => $teacher->id,
                'normative_profile_id' => $profile->id,
                'name' => 'Libro activable',
                'modality' => 'regular',
            ])->assertCreated();

        $this->mock(CompliancePreflightService::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andReturn([
                'ready' => true,
                'core_ready' => true,
                'module_enabled' => true,
                'capabilities' => [],
                'checks' => [],
                'blockers' => [],
            ]);
        });

        $this->withHeader('Idempotency-Key', 'lcd-book-bulk-open-0001')
            ->postJson('/api/libro-digital/v1/books/bulk-open', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'books' => [[
                    'id' => $created->json('data.id'),
                    'lock_version' => $created->json('data.lock_version'),
                ]],
            ])->assertOk()
            ->assertJsonPath('meta.opened', 1)
            ->assertJsonPath('meta.failed', 0)
            ->assertJsonPath('data.0.result', 'opened')
            ->assertJsonPath('data.0.status', 'open');

        $book = Book::query()->findOrFail($created->json('data.id'));
        $this->assertSame('open', $book->status->value);
        $this->assertSame(3, (int) $book->lock_version);
        $this->assertDatabaseHas('lcd_audit_events', [
            'auditable_id' => $book->id,
            'event' => 'lcd.book.status_changed',
            'action' => 'pending_preflight',
            'revision' => 2,
        ]);
        $this->assertDatabaseHas('lcd_audit_events', [
            'auditable_id' => $book->id,
            'event' => 'lcd.book.status_changed',
            'action' => 'open',
            'revision' => 3,
        ]);
    }

    public function test_bulk_book_activation_reuses_one_institutional_preflight_for_thirty_four_books(): void
    {
        [$user, $school, $year, $course, $firstSubject, $teacher, $profile] = $this->context();
        $provisioning = app(BookProvisioningService::class);
        $books = collect();

        foreach (range(1, 34) as $index) {
            $subject = $index === 1
                ? $firstSubject
                : ScheduleSubject::query()->create([
                    'name' => 'Asignatura masiva '.$index,
                    'code' => 'MAS-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'area' => 'Prueba masiva',
                    'color' => '#405189',
                    'active' => true,
                ]);
            $books->push($provisioning->provision(
                $school,
                $year,
                $course,
                $subject,
                $profile,
                $user,
                $teacher,
            ));
        }

        $this->mock(CompliancePreflightService::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andReturn([
                'ready' => true,
                'core_ready' => true,
                'module_enabled' => true,
                'capabilities' => [],
                'checks' => [],
                'blockers' => [],
            ]);
        });

        $response = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'lcd-book-bulk-open-34-0001')
            ->postJson('/api/libro-digital/v1/books/bulk-open', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'books' => $books->map(fn (Book $book): array => [
                    'id' => $book->id,
                    'lock_version' => $book->lock_version,
                ])->all(),
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.requested', 34)
            ->assertJsonPath('meta.opened', 34)
            ->assertJsonPath('meta.failed', 0)
            ->assertJsonCount(34, 'data');
        $this->assertSame(34, Book::query()->where('status', 'open')->count());
        $this->assertSame(
            68,
            DB::table('lcd_audit_events')
                ->where('event', 'lcd.book.status_changed')
                ->whereIn('auditable_id', $books->pluck('id'))
                ->count(),
        );
    }

    public function test_bulk_book_activation_can_audit_an_explicit_pending_teacher_warning(): void
    {
        [$user, $school, $year, $course, $subject, , $profile] = $this->context();
        $book = app(BookProvisioningService::class)->provision(
            $school,
            $year,
            $course,
            $subject,
            $profile,
            $user,
            null,
        );

        $this->mock(CompliancePreflightService::class, function ($mock): void {
            $mock->shouldReceive('run')->twice()->andReturn([
                'ready' => true,
                'core_ready' => true,
                'module_enabled' => true,
                'capabilities' => [],
                'checks' => [],
                'blockers' => [],
            ]);
        });

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'lcd-book-bulk-open-teacher-required-0001')
            ->postJson('/api/libro-digital/v1/books/bulk-open', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'books' => [['id' => $book->id, 'lock_version' => $book->lock_version]],
            ])->assertOk()
            ->assertJsonPath('meta.opened', 0)
            ->assertJsonPath('meta.failed', 1)
            ->assertJsonPath('data.0.details.1.code', 'teacher_assignment')
            ->assertJsonPath('data.0.details.1.status', 'blocked');

        $response = $this->withHeader('Idempotency-Key', 'lcd-book-bulk-open-teacher-warning-0001')
            ->postJson('/api/libro-digital/v1/books/bulk-open', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'allow_unassigned_teacher' => true,
                'books' => [['id' => $book->id, 'lock_version' => $book->lock_version]],
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.opened', 1)
            ->assertJsonPath('meta.opened_without_teacher', 1)
            ->assertJsonPath('meta.failed', 0)
            ->assertJsonPath('data.0.result', 'opened')
            ->assertJsonPath('data.0.teacher_assignment_pending', true)
            ->assertJsonPath('data.0.warnings.0.code', 'teacher_assignment')
            ->assertJsonPath('data.0.warnings.0.status', 'warning');

        $book->refresh();
        $this->assertSame('open', $book->status->value);
        $event = DB::table('lcd_audit_events')
            ->where('auditable_id', $book->id)
            ->where('event', 'lcd.book.status_changed')
            ->where('action', 'open')
            ->latest('id')
            ->first();
        $this->assertNotNull($event?->encrypted_diff);
        $diff = json_decode(Crypt::decryptString($event->encrypted_diff), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($diff['teacher_assignment_pending']['after']);
    }

    public function test_ede_standard_can_be_imported_during_disabled_rollout_and_multipart_idempotency_hashes_files(): void
    {
        Storage::fake('local');
        config(['libro_digital.storage.disk' => 'local']);
        [$user, $school] = $this->context();
        FeatureFlag::query()->where('school_id', $school->id)->where('code', 'lcd_enabled')->update(['enabled' => false]);

        $mapping = json_encode([[
            'code' => 'BOOK-CODE',
            'source_entity' => 'book',
            'source_field' => 'code',
            'target_record_type' => 'Book',
            'target_field' => 'Code',
            'data_type' => 'string',
            'transform_definition' => ['type' => 'direct'],
        ]], JSON_THROW_ON_ERROR);
        $payload = static fn (string $source): array => [
            'school_id' => $school->id,
            'version' => '7.1-rollout-test',
            'code' => 'CEDS-ROLLOUT-TEST',
            'authority' => 'MINEDUC (prueba sintética)',
            'source_url' => 'https://example.test/standard',
            'effective_from' => '2035-03-01',
            'source' => UploadedFile::fake()->createWithContent('source.xlsx', $source),
            'schema' => UploadedFile::fake()->createWithContent('schema.xlsx', 'synthetic-schema'),
            'mappings' => UploadedFile::fake()->createWithContent('mappings.json', $mapping),
        ];

        $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-ede-rollout-import-0001')
            ->post('/api/libro-digital/v1/ede/import-standard', $payload('synthetic-source-v1'))
            ->assertCreated()->assertJsonPath('data.status', 'imported');

        $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-ede-rollout-import-0001')
            ->post('/api/libro-digital/v1/ede/import-standard', $payload('synthetic-source-v2'))
            ->assertConflict()->assertJsonPath('code', 'LCD_IDEMPOTENCY_KEY_REUSED');
    }

    /** @return array{User, School, AcademicYear, CourseSection, ScheduleSubject, Staff, RegulatoryProfile} */
    private function context(): array
    {
        $user = User::factory()->create(['active' => true]);
        $superAdmin = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $user->roles()->syncWithoutDetaching([$superAdmin->id]);
        $school = School::query()->create(['rbd' => '12345-6', 'name' => 'Escuela Prueba', 'timezone' => 'America/Santiago', 'active' => true]);
        $year = AcademicYear::factory()->create(['year' => 2035, 'name' => '2035', 'starts_at' => '2035-03-01', 'ends_at' => '2035-12-20', 'is_active' => true]);
        $level = EducationLevel::factory()->create(['name' => 'Quinto básico de prueba', 'order' => 505, 'type' => 'basica']);
        $course = CourseSection::factory()->create(['academic_year_id' => $year->id, 'education_level_id' => $level->id, 'display_name' => '5° Básico A', 'section_name' => 'A']);
        $subject = ScheduleSubject::query()->create(['name' => 'Lenguaje', 'code' => 'LEN-05', 'area' => 'Lenguaje', 'color' => '#405189', 'active' => true]);
        $teacher = Staff::query()->create(['full_name' => 'Docente Prueba', 'rut' => '12.345.678-5', 'active' => true]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-LCD', 'name' => 'Perfil normativo', 'version' => '1.0', 'effective_from' => '2030-01-01',
            'retention_years' => 6, 'rules_snapshot' => [], 'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id, 'rbd_snapshot' => $school->rbd, 'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone, 'active' => true,
        ]);
        FeatureFlag::query()->create(['school_id' => $school->id, 'scope_key' => 'school:'.$school->id, 'code' => 'lcd_enabled', 'enabled' => true]);

        return [$user, $school, $year, $course, $subject, $teacher, $profile];
    }
}
