<?php

namespace Tests\Feature\PedagogicalManagement;

use App\Jobs\PedagogicalManagement\GeneratePedagogicalAiReportJob;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\PedagogicalManagement\PedagogicalAiReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PedagogicalAiWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_receives_a_direct_ai_workspace_surface(): void
    {
        $role = Role::query()
            ->where('slug', 'coordinadora_academica')
            ->with('permissions', 'modules')
            ->firstOrFail();

        $this->assertContains('pedagogical-instruments.ai-workspace', $role->permissions->pluck('slug'));
        $module = $role->modules->firstWhere('slug', 'pedagogical_ai_workspace');
        $this->assertNotNull($module);
        $this->assertSame('Revisión con IA', $module->name);
        $this->assertSame('/gestion-pedagogica/revision-ia', $module->frontend_route);

        $teacher = Role::query()->where('slug', 'docente')->with('permissions', 'modules')->firstOrFail();
        $this->assertNotContains('pedagogical-instruments.ai-workspace', $teacher->permissions->pluck('slug'));
        $this->assertNotContains('pedagogical_ai_workspace', $teacher->modules->pluck('slug'));
    }

    public function test_coordinator_reviews_a_file_in_place_without_creating_a_teacher_submission(): void
    {
        Queue::fake();
        Storage::fake('local');
        config()->set('pedagogical_management.openai.api_key', 'test-key');
        config()->set('pedagogical_management.openai.model', 'gpt-test');
        [$coordinator, $school, $course, $subject] = $this->context();

        $catalogs = $this->actingAs($coordinator)
            ->getJson('/api/pedagogical-management/ai-workspace/catalogs')
            ->assertOk();
        $this->assertSame($school->id, $catalogs->json('data.school.id'));
        $this->assertSame($course->id, $catalogs->json('data.courses.0.id'));
        $this->assertTrue($catalogs->json('data.openai_configured'));

        $response = $this->actingAs($coordinator)
            ->post('/api/pedagogical-management/ai-workspace/reviews', [
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'course_id' => $course->id,
                'file' => UploadedFile::fake()->createWithContent(
                    'instrumento-coordinacion.pdf',
                    $this->syntheticPdf('INSTRUMENTO PARA REVISION AUTONOMA'),
                ),
            ])
            ->assertAccepted()
            ->assertJsonPath('data.workflow_status', 'draft')
            ->assertJsonPath('data.owner.id', $coordinator->id)
            ->assertJsonPath('data.latest_ai_report.status', 'pending');

        $instrumentId = $response->json('data.id');
        $instrument = PedagogicalInstrument::query()->where('uuid', $instrumentId)->firstOrFail();
        $this->assertNull($instrument->submitted_at);
        $this->assertSame($coordinator->id, $instrument->created_by);
        $this->assertSame('draft', $instrument->workflow_status->value);
        Queue::assertPushed(GeneratePedagogicalAiReportJob::class);

        $report = $instrument->aiReports()->firstOrFail()->load([
            'instrument.owner:id,name',
            'instrument.subject:id,name',
            'instrument.courses:id,display_name',
            'instrumentFile',
        ]);
        $payloadMethod = new \ReflectionMethod(PedagogicalAiReportService::class, 'responsePayload');
        $payload = $payloadMethod->invoke(
            app(PedagogicalAiReportService::class),
            $report,
            'file-test',
        );
        $prompt = $payload['input'][1]['content'][1]['text'];
        $this->assertStringContainsString(
            'Revisión autónoma de coordinación. Responsable: '.$coordinator->name.'.',
            $prompt,
        );
        $this->assertStringNotContainsString('Docente: '.$coordinator->name.'.', $prompt);

        $this->actingAs($coordinator)
            ->getJson('/api/pedagogical-management/ai-workspace/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $instrumentId);

        $this->actingAs($coordinator)
            ->getJson('/api/pedagogical-management/document-review?per_page=100')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_each_coordinator_can_only_open_her_own_workspace_reviews(): void
    {
        Queue::fake();
        Storage::fake('local');
        config()->set('pedagogical_management.openai.api_key', 'test-key');
        config()->set('pedagogical_management.openai.model', 'gpt-test');
        [$coordinator, $school, $course, $subject] = $this->context();

        $instrumentId = $this->actingAs($coordinator)
            ->post('/api/pedagogical-management/ai-workspace/reviews', [
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'course_id' => $course->id,
                'file' => UploadedFile::fake()->createWithContent(
                    'revision-privada.pdf',
                    $this->syntheticPdf('REVISION PRIVADA DE COORDINACION'),
                ),
            ])
            ->assertAccepted()
            ->json('data.id');

        $otherCoordinator = User::factory()->create(['active' => true]);
        $otherCoordinator->roles()->sync([
            Role::query()->where('slug', 'coordinadora_academica')->firstOrFail()->id,
        ]);
        $school->users()->attach($otherCoordinator->id, [
            'active' => true,
            'role_snapshot' => 'Coordinadora académica',
        ]);

        $this->actingAs($otherCoordinator)
            ->getJson('/api/pedagogical-management/ai-workspace/reviews/'.$instrumentId)
            ->assertNotFound();
        $this->actingAs($otherCoordinator)
            ->getJson('/api/pedagogical-management/ai-workspace/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $teacher = User::factory()->create(['active' => true]);
        $teacher->roles()->sync([Role::query()->where('slug', 'docente')->firstOrFail()->id]);
        $school->users()->attach($teacher->id, ['active' => true, 'role_snapshot' => 'Docente']);
        $this->actingAs($teacher)
            ->getJson('/api/pedagogical-management/ai-workspace/reviews')
            ->assertForbidden();
    }

    /** @return array{User,School,CourseSection,ScheduleSubject} */
    private function context(): array
    {
        $coordinator = User::factory()->create(['active' => true]);
        $coordinator->roles()->sync([
            Role::query()->where('slug', 'coordinadora_academica')->firstOrFail()->id,
        ]);
        $school = School::query()->create([
            'rbd' => '56666-6',
            'name' => 'Escuela revisión IA',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $school->users()->attach($coordinator->id, [
            'active' => true,
            'role_snapshot' => 'Coordinadora académica',
        ]);
        $year = AcademicYear::factory()->create([
            'year' => 2037,
            'name' => '2037',
            'starts_at' => '2037-03-01',
            'ends_at' => '2037-12-20',
            'is_active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => 2037,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);
        $level = EducationLevel::factory()->create([
            'name' => 'Sexto básico IA',
            'order' => 906,
            'type' => 'basica',
        ]);
        $course = CourseSection::factory()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'display_name' => '6° Básico A',
            'section_name' => 'A',
            'active' => true,
        ]);
        $subject = ScheduleSubject::query()->create([
            'name' => 'Lenguaje IA',
            'code' => 'LEN-IA',
            'area' => 'Lenguaje',
            'color' => '#176b76',
            'active' => true,
        ]);

        return [$coordinator, $school, $course, $subject];
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
}
