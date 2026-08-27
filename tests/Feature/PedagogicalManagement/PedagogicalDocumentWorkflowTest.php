<?php

namespace Tests\Feature\PedagogicalManagement;

use App\Jobs\PedagogicalManagement\GeneratePedagogicalAiReportJob;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\PedagogicalCoordinatorAssignment;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentAiReport;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\PedagogicalManagement\PedagogicalAiReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PedagogicalDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_docente_and_academic_coordination_roles_receive_only_their_workflow_surfaces(): void
    {
        $teacher = Role::query()->where('slug', 'docente')->with('permissions', 'modules')->firstOrFail();
        $teacherPermissions = $teacher->permissions->pluck('slug');
        foreach ([
            'pedagogical-instruments.view',
            'pedagogical-instruments.create',
            'pedagogical-instruments.update',
            'pedagogical-instruments.download',
        ] as $permission) {
            $this->assertContains($permission, $teacherPermissions);
        }
        $this->assertNotContains('pedagogical-instruments.decide', $teacherPermissions);
        $this->assertNotContains('pedagogical-instruments.ai-report', $teacherPermissions);
        $this->assertEqualsCanonicalizing(
            ['pedagogical_management', 'pedagogical_my_instruments'],
            $teacher->modules->whereIn('slug', ['pedagogical_management', 'pedagogical_my_instruments', 'pedagogical_document_review', 'pedagogical_coordinator_assignments'])->pluck('slug')->all(),
        );

        $coordinator = Role::query()->where('slug', 'coordinadora_academica')->with('permissions', 'modules')->firstOrFail();
        $this->assertContains('pedagogical-instruments.decide', $coordinator->permissions->pluck('slug'));
        $this->assertContains('pedagogical-instruments.ai-report', $coordinator->permissions->pluck('slug'));
        $this->assertContains('pedagogical_document_review', $coordinator->modules->pluck('slug'));
        $this->assertNotContains('pedagogical_coordinator_assignments', $coordinator->modules->pluck('slug'));
    }

    public function test_rectification_history_approval_and_print_center_are_end_to_end(): void
    {
        Storage::fake('local');
        [$teacher, $coordinator, $school, $year, $course, $otherCourse, $subject] = $this->context();

        $instrumentId = $this->submit($teacher, $school, $course, $subject, 'evaluacion-inicial.pdf', 'VERSION UNO')
            ->assertCreated()
            ->assertJsonPath('data.instrument.workflow_status', 'submitted')
            ->json('data.instrument.id');
        $otherInstrumentId = $this->submit($teacher, $school, $otherCourse, $subject, 'evaluacion-otro-curso.pdf', 'OTRO CURSO')
            ->assertCreated()
            ->json('data.instrument.id');

        $configurator = User::factory()->create(['active' => true]);
        $configurator->roles()->sync([Role::query()->where('slug', 'super_admin')->firstOrFail()->id]);
        $this->actingAs($configurator)
            ->getJson('/api/pedagogical-management/coordinator-assignments?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonPath('data.coordinators.0.id', $coordinator->id);
        $this->actingAs($configurator)
            ->putJson('/api/pedagogical-management/coordinator-assignments', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'coordinator_user_id' => $coordinator->id,
                'course_ids' => [$course->id],
                'education_level_ids' => [],
            ])
            ->assertOk();
        $this->assertDatabaseHas('pedagogical_coordinator_assignments', [
            'coordinator_user_id' => $coordinator->id,
            'target_type' => PedagogicalCoordinatorAssignment::TARGET_COURSE,
            'target_id' => $course->id,
        ]);

        $queue = $this->actingAs($coordinator)
            ->getJson('/api/pedagogical-management/document-review?per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertSame($instrumentId, $queue->json('data.0.id'));
        $this->actingAs($coordinator)
            ->getJson('/api/pedagogical-management/instruments/'.$otherInstrumentId)
            ->assertForbidden();

        $guidanceId = $this->actingAs($coordinator)
            ->postJson('/api/pedagogical-management/guidance-documents', [
                'school_id' => $school->id,
                'document_type' => 'evaluation_regulation',
                'title' => 'Reglamento de evaluación',
                'description' => 'Orientaciones vigentes',
                'content' => 'Revisar el artículo institucional aplicable antes de reenviar.',
                'active' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($coordinator)
            ->postJson('/api/pedagogical-management/instruments/'.$instrumentId.'/reviews', [
                'decision' => 'rectification_requested',
                'coordinator_notes' => 'Ajustar la rúbrica y explicitar los criterios de logro.',
                'share_ai_report' => false,
                'guidance_document_ids' => [$guidanceId],
            ])
            ->assertCreated()
            ->assertJsonPath('data.decision', 'rectification_requested');

        $this->actingAs($teacher)
            ->getJson('/api/pedagogical-management/instruments/'.$instrumentId)
            ->assertOk()
            ->assertJsonPath('data.workflow_status', 'rectification_requested')
            ->assertJsonPath('data.latest_review.coordinator_notes', 'Ajustar la rúbrica y explicitar los criterios de logro.')
            ->assertJsonPath('data.latest_review.guidance_documents.0.title', 'Reglamento de evaluación');

        $this->actingAs($teacher)
            ->post('/api/pedagogical-management/instruments/'.$instrumentId.'/files', [
                'file' => UploadedFile::fake()->createWithContent('evaluacion-rectificada.pdf', $this->syntheticPdf('VERSION DOS RECTIFICADA')),
            ])
            ->assertCreated()
            ->assertJsonPath('data.file.version', 2);

        $this->actingAs($coordinator)
            ->postJson('/api/pedagogical-management/instruments/'.$instrumentId.'/reviews', [
                'decision' => 'approved_with_observations',
                'coordinator_notes' => 'Aprobado. Mantener esta rúbrica en la aplicación.',
                'share_ai_report' => false,
                'guidance_document_ids' => [],
            ])
            ->assertCreated()
            ->assertJsonPath('data.decision', 'approved_with_observations');

        $instrument = PedagogicalInstrument::query()->where('uuid', $instrumentId)->firstOrFail();
        $this->assertSame('approved_with_observations', $instrument->workflow_status->value);
        $this->assertCount(2, $instrument->files);
        $printRequest = $instrument->printRequests()->with('instrumentFile')->firstOrFail();
        $this->assertSame(2, $printRequest->instrumentFile->version);
        $this->assertSame('pending', $printRequest->status->value);

        $centerUser = $this->userWithPermissions([
            'pedagogical-print-requests.view',
            'pedagogical-print-requests.download',
            'pedagogical-print-requests.print',
            'pedagogical-print-requests.complete',
        ], 'centro_apuntes_workflow_test');
        $this->actingAs($centerUser)
            ->getJson('/api/pedagogical-print-center/requests')
            ->assertOk()
            ->assertJsonPath('data.0.file.version', 2)
            ->assertJsonPath('data.0.instrument.owner.name', $teacher->name);
        $this->actingAs($centerUser)
            ->get('/api/pedagogical-print-center/requests/'.$printRequest->uuid.'/file?download=1')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($centerUser)
            ->postJson('/api/pedagogical-print-center/requests/'.$printRequest->uuid.'/actions', ['action' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_openai_report_accepts_docx_and_always_evaluates_the_institutional_guideline(): void
    {
        Queue::fake();
        Storage::fake('local');
        config()->set('pedagogical_management.openai.api_key', 'test-key');
        config()->set('pedagogical_management.openai.model', 'gpt-test');
        [$teacher, $coordinator, $school, $year, $course, $otherCourse, $subject] = $this->context();
        PedagogicalCoordinatorAssignment::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'coordinator_user_id' => $coordinator->id,
            'target_type' => PedagogicalCoordinatorAssignment::TARGET_LEVEL,
            'target_id' => $course->education_level_id,
            'assigned_by' => $coordinator->id,
        ]);
        $instrumentId = $this->submitDocx($teacher, $school, $course, $subject, 'instrumento-openai.docx', 'INSTRUMENTO WORD PARA ANALISIS DOCUMENTAL')
            ->assertCreated()->json('data.instrument.id');

        $reportId = $this->actingAs($coordinator)
            ->postJson('/api/pedagogical-management/instruments/'.$instrumentId.'/ai-reports')
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');
        Queue::assertPushed(GeneratePedagogicalAiReportJob::class);

        $structured = [
            'executive_summary' => 'El instrumento es coherente y requiere precisar un criterio.',
            'criteria_assessment' => collect(config('pedagogical_management.review_criteria'))
                ->map(fn (array $criterion): array => [
                    'code' => $criterion['code'],
                    'dimension' => $criterion['dimension'],
                    'applicability' => $criterion['applicability'],
                    'criterion' => $criterion['criterion'],
                    'status' => match ($criterion['code']) {
                        '3.1' => 'partially_meets',
                        '5.1', '5.2' => 'not_applicable',
                        default => 'meets',
                    },
                    'finding' => $criterion['code'] === '3.1' ? 'Una instrucción requiere mayor precisión.' : 'Se observa evidencia suficiente.',
                    'evidence' => $criterion['code'] === '3.1' ? 'Responda correctamente.' : null,
                    'recommendation' => $criterion['code'] === '3.1' ? 'Precisar la acción esperada.' : null,
                    'improvement_example' => $criterion['code'] === '3.1'
                        ? 'Reemplazar por: “Explica dos causas y fundamenta cada una con un dato del texto”.'
                        : 'Mantener el criterio y añadir un ejemplo breve de la respuesta esperada.',
                    'page' => null,
                ])->all(),
            'strengths' => ['Consignas claras'],
            'observations' => [[
                'category' => 'rubrica', 'severity' => 'important', 'title' => 'Criterio',
                'description' => 'Falta precisión.', 'recommendation' => 'Añadir descriptor.',
                'evidence' => null, 'page' => 1,
            ]],
            'miscellaneous_findings' => [[
                'category' => 'arithmetic', 'severity' => 'important', 'title' => 'Total de puntajes inconsistente',
                'finding' => 'Los ítems visibles suman 28 puntos, pero el encabezado declara 30.',
                'evidence' => '5 + 8 + 15 = 28 puntos; total declarado: 30 puntos.',
                'recommendation' => 'Corregir el total declarado o redistribuir los dos puntos faltantes.',
                'improvement_example' => 'Cambiar “Puntaje total: 30” por “Puntaje total: 28”.',
                'page' => 1,
            ]],
            'recommendations' => ['Añadir descriptor observable'],
            'suggested_teacher_message' => 'Por favor, precise el descriptor de logro.',
        ];
        Http::fake(function (Request $request) use ($structured) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/files')) {
                return Http::response(['id' => 'file_test'], 200);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/responses')) {
                return Http::response([
                    'id' => 'resp_test',
                    'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($structured)]]]],
                    'usage' => ['input_tokens' => 100, 'output_tokens' => 80],
                ], 200);
            }

            return Http::response([], 200);
        });

        $report = PedagogicalInstrumentAiReport::query()->where('uuid', $reportId)->firstOrFail();
        app(PedagogicalAiReportService::class)->process($report);
        $this->assertSame('completed', $report->fresh()->status->value);
        $this->assertSame($structured['suggested_teacher_message'], $report->fresh()->report['suggested_teacher_message']);
        $this->assertSame(
            array_column(config('pedagogical_management.review_criteria'), 'code'),
            array_column($report->fresh()->report['criteria_assessment'], 'code'),
        );
        $this->assertSame(
            array_column(config('pedagogical_management.review_criteria'), 'applicability'),
            array_column($report->fresh()->report['criteria_assessment'], 'applicability'),
        );
        $this->assertSame('not_applicable', collect($report->fresh()->report['criteria_assessment'])->firstWhere('code', '5.1')['status']);
        $this->assertSame(1, $report->fresh()->report['summary_statistics']['partially_meets']);
        $this->assertSame(1, $report->fresh()->report['summary_statistics']['miscellaneous_findings']);
        $this->assertSame(
            'Reemplazar por: “Explica dos causas y fundamenta cada una con un dato del texto”.',
            collect($report->fresh()->report['criteria_assessment'])->firstWhere('code', '3.1')['improvement_example'],
        );
        $storedFile = PedagogicalInstrument::query()->where('uuid', $instrumentId)->firstOrFail()->files()->firstOrFail();
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $storedFile->mime_type);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/responses')
            && $request['store'] === false
            && $request['input'][1]['content'][0]['type'] === 'input_file'
            && str_contains($request['input'][1]['content'][1]['text'], 'Pauta institucional obligatoria y consideraciones EPA:')
            && str_contains($request['input'][1]['content'][1]['text'], '2.1 [Alineación curricular]')
            && str_contains($request['input'][1]['content'][1]['text'], '3.4 [Calidad del instrumento]')
            && str_contains($request['input'][1]['content'][1]['text'], '4.3 [Acceso universal y presentación]')
            && str_contains($request['input'][1]['content'][1]['text'], '7.1 [Metacognición y autorregulación]')
            && str_contains($request['input'][1]['content'][1]['text'], '[Aplicación: Evaluaciones escritas tipo prueba]')
            && str_contains($request['input'][1]['content'][1]['text'], 'Formato: DOCX')
            && $request['text']['format']['schema']['properties']['criteria_assessment']['minItems'] === count(config('pedagogical_management.review_criteria'))
            && in_array('improvement_example', $request['text']['format']['schema']['properties']['criteria_assessment']['items']['required'], true)
            && in_array('miscellaneous_findings', $request['text']['format']['schema']['required'], true)
            && $request['text']['format']['schema']['properties']['miscellaneous_findings']['maxItems'] === 12
            && in_array('not_applicable', $request['text']['format']['schema']['properties']['criteria_assessment']['items']['properties']['status']['enum'], true)
            && $request['text']['format']['strict'] === true
        );

        $this->actingAs($coordinator)
            ->postJson('/api/pedagogical-management/instruments/'.$instrumentId.'/reviews', [
                'decision' => 'approved',
                'ai_report_id' => $reportId,
                'share_ai_report' => true,
                'guidance_document_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('share_ai_report');

        $this->actingAs($coordinator)
            ->postJson('/api/pedagogical-management/instruments/'.$instrumentId.'/reviews', [
                'decision' => 'rectification_requested',
                'coordinator_notes' => 'Precisa el descriptor de logro.',
                'ai_report_id' => $reportId,
                'share_ai_report' => true,
                'guidance_document_ids' => [],
            ])
            ->assertCreated()
            ->assertJsonPath('data.can_download_ai_report', true)
            ->assertJsonPath('data.ai_report.report.miscellaneous_findings.0.title', 'Total de puntajes inconsistente');
        $this->actingAs($teacher)
            ->getJson('/api/pedagogical-management/instruments/'.$instrumentId)
            ->assertOk()
            ->assertJsonPath('data.latest_review.share_ai_report', true)
            ->assertJsonPath('data.latest_review.can_download_ai_report', true)
            ->assertJsonPath('data.latest_review.ai_report.report.executive_summary', $structured['executive_summary'])
            ->assertJsonPath('data.latest_review.ai_report.report.summary_statistics.miscellaneous_findings', 1)
            ->assertJsonPath('data.latest_review.ai_report.report.criteria_assessment.4.improvement_example', 'Reemplazar por: “Explica dos causas y fundamenta cada una con un dato del texto”.');
        $this->assertDatabaseHas('pedagogical_report_snapshots', [
            'ai_report_id' => $report->id,
            'decision' => 'rectification_requested',
            'criteria_total' => count(config('pedagogical_management.review_criteria')),
            'miscellaneous_count' => 1,
        ]);
        $this->actingAs($teacher)
            ->getJson('/api/pedagogical-management/instruments/'.$instrumentId.'/ai-reports/'.$reportId)
            ->assertForbidden();
    }

    private function submit(User $teacher, School $school, CourseSection $course, ScheduleSubject $subject, string $filename, string $content)
    {
        return $this->actingAs($teacher)->post('/api/pedagogical-management/instruments', [
            'school_id' => $school->id,
            'owner_user_id' => $teacher->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
            'file' => UploadedFile::fake()->createWithContent($filename, $this->syntheticPdf($content)),
        ]);
    }

    private function submitDocx(User $teacher, School $school, CourseSection $course, ScheduleSubject $subject, string $filename, string $content)
    {
        return $this->actingAs($teacher)->post('/api/pedagogical-management/instruments', [
            'school_id' => $school->id,
            'owner_user_id' => $teacher->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
            'file' => UploadedFile::fake()->createWithContent($filename, $this->syntheticDocx($content)),
        ]);
    }

    /** @return array{User,User,School,AcademicYear,CourseSection,CourseSection,ScheduleSubject} */
    private function context(): array
    {
        $teacher = User::factory()->create(['active' => true]);
        $coordinator = User::factory()->create(['active' => true]);
        $teacher->roles()->sync([Role::query()->where('slug', 'docente')->firstOrFail()->id]);
        $coordinator->roles()->sync([Role::query()->where('slug', 'coordinadora_academica')->firstOrFail()->id]);
        $school = School::query()->create(['rbd' => '55555-5', 'name' => 'Escuela de flujo', 'timezone' => 'America/Santiago', 'active' => true]);
        $school->users()->attach($teacher->id, ['active' => true, 'role_snapshot' => 'Docente']);
        $school->users()->attach($coordinator->id, ['active' => true, 'role_snapshot' => 'Coordinadora académica']);
        $year = AcademicYear::factory()->create(['year' => 2036, 'name' => '2036', 'starts_at' => '2036-03-01', 'ends_at' => '2036-12-20', 'is_active' => true]);
        $school->academicYears()->attach($year->id, ['rbd_snapshot' => $school->rbd, 'year_snapshot' => 2036, 'timezone_snapshot' => $school->timezone, 'active' => true]);
        $level = EducationLevel::factory()->create(['name' => 'Séptimo básico de flujo', 'order' => 907, 'type' => 'basica']);
        $otherLevel = EducationLevel::factory()->create(['name' => 'Octavo básico de flujo', 'order' => 908, 'type' => 'basica']);
        $course = CourseSection::factory()->create(['academic_year_id' => $year->id, 'education_level_id' => $level->id, 'display_name' => '7° Básico A', 'section_name' => 'A', 'active' => true]);
        $otherCourse = CourseSection::factory()->create(['academic_year_id' => $year->id, 'education_level_id' => $otherLevel->id, 'display_name' => '8° Básico B', 'section_name' => 'B', 'active' => true]);
        $subject = ScheduleSubject::query()->create(['name' => 'Lenguaje', 'code' => 'LEN-07', 'area' => 'Lenguaje', 'color' => '#176b76', 'active' => true]);

        return [$teacher, $coordinator, $school, $year, $course, $otherCourse, $subject];
    }

    private function userWithPermissions(array $slugs, string $roleSlug): User
    {
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug, 'active' => true]);
        $role->permissions()->sync(Permission::query()->whereIn('slug', $slugs)->pluck('id'));
        $user = User::factory()->create(['active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function syntheticPdf(string $streamText): string
    {
        $stream = 'BT /F1 12 Tf 45 720 Td ('.$streamText.') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream).">>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($index = 1; $index <= 5; $index++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$index])."\n";
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function syntheticDocx(string $documentText): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pedagogical-docx-');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($documentText, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $document = file_get_contents($path);
        @unlink($path);

        return is_string($document) ? $document : '';
    }
}
