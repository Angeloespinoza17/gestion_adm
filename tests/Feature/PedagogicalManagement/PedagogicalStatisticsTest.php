<?php

namespace Tests\Feature\PedagogicalManagement;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\PedagogicalCoordinatorAssignment;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentAiReport;
use App\Models\PedagogicalManagement\PedagogicalInstrumentFile;
use App\Models\PedagogicalManagement\PedagogicalInstrumentReview;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\PedagogicalManagement\PedagogicalReportProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PedagogicalStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_quantify_evolution_and_respect_coordinator_scope(): void
    {
        [$teacher, $coordinator, $school, $year, $course, $otherCourse, $subject] = $this->context();
        PedagogicalCoordinatorAssignment::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'coordinator_user_id' => $coordinator->id,
            'target_type' => PedagogicalCoordinatorAssignment::TARGET_COURSE,
            'target_id' => $course->id,
            'assigned_by' => $coordinator->id,
        ]);

        $instrument = $this->instrument($teacher, $school, $year, $course, $subject, 'Prueba con trayectoria');
        $first = $this->officialReport($instrument, $teacher, 1, 'rectification_requested', [
            '2.1' => 'partially_meets',
            '3.1' => 'does_not_meet',
        ], '2026-04-04 10:00:00', true);
        $second = $this->officialReport($instrument, $teacher, 2, 'approved_with_observations', [
            '2.1' => 'meets',
            '3.1' => 'partially_meets',
        ], '2026-04-12 10:00:00');

        $hidden = $this->instrument($teacher, $school, $year, $otherCourse, $subject, 'Instrumento fuera del alcance');
        $this->officialReport($hidden, $teacher, 1, 'approved', ['2.1' => 'does_not_meet'], '2026-04-09 10:00:00');

        $response = $this->actingAs($coordinator)->getJson('/api/pedagogical-management/statistics?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonPath('data.summary.official_reports', 2)
            ->assertJsonPath('data.summary.instruments', 1)
            ->assertJsonPath('data.summary.teachers', 1)
            ->assertJsonPath('data.summary.first_pass_approval_rate', 0)
            ->assertJsonPath('data.summary.rectification_closure_rate', 100)
            ->assertJsonPath('data.summary.comparable_pairs', 1)
            ->assertJsonPath('data.instruments.0.id', $instrument->uuid)
            ->assertJsonPath('data.instruments.0.reviewed_versions', 2)
            ->assertJsonPath('data.miscellaneous.0.category', 'arithmetic')
            ->assertJsonPath('data.miscellaneous.0.findings', 1);

        $criteria = collect($response->json('data.criteria'))->keyBy('code');
        $this->assertSame(100.0, (float) $criteria['2.1']['resolution_rate']);
        $this->assertSame(100.0, (float) $criteria['3.1']['persistence_rate']);
        $this->assertGreaterThan((float) $first->compliance_percentage, (float) $second->compliance_percentage);

        $this->actingAs($coordinator)
            ->getJson('/api/pedagogical-management/statistics/instruments/'.$instrument->uuid)
            ->assertOk()
            ->assertJsonPath('data.summary.reviewed_versions', 2)
            ->assertJsonPath('data.transitions.0.resolved.0', '2.1')
            ->assertJsonPath('data.transitions.0.persistent.0', '3.1');
        $this->actingAs($coordinator)
            ->getJson('/api/pedagogical-management/statistics/instruments/'.$hidden->uuid)
            ->assertForbidden();
        $this->actingAs($teacher)
            ->getJson('/api/pedagogical-management/statistics?school_id='.$school->id)
            ->assertForbidden();
    }

    public function test_statistics_permission_and_navigation_are_not_granted_to_teachers(): void
    {
        $teacher = Role::query()->where('slug', 'docente')->with('permissions', 'modules')->firstOrFail();
        $coordinator = Role::query()->where('slug', 'coordinadora_academica')->with('permissions', 'modules')->firstOrFail();

        $this->assertNotContains('pedagogical-instruments.statistics', $teacher->permissions->pluck('slug'));
        $this->assertNotContains('pedagogical_statistics', $teacher->modules->pluck('slug'));
        $this->assertContains('pedagogical-instruments.statistics', $coordinator->permissions->pluck('slug'));
        $this->assertContains('pedagogical_statistics', $coordinator->modules->pluck('slug'));
    }

    private function officialReport(
        PedagogicalInstrument $instrument,
        User $teacher,
        int $version,
        string $decision,
        array $statuses,
        string $reviewedAt,
        bool $miscellaneous = false,
    ): object {
        $submittedAt = Carbon::parse($reviewedAt)->subDays(2);
        $file = PedagogicalInstrumentFile::query()->create([
            'school_id' => $instrument->school_id,
            'instrument_id' => $instrument->id,
            'version' => $version,
            'original_filename' => "version-{$version}.pdf",
            'internal_filename' => "version-{$version}.pdf",
            'storage_disk' => 'local',
            'storage_path' => "tests/version-{$instrument->id}-{$version}.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'sha256' => hash('sha256', $instrument->id.'-'.$version),
            'uploaded_by' => $teacher->id,
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ]);
        $criteria = collect(config('pedagogical_management.review_criteria'))->map(function (array $criterion) use ($statuses): array {
            $status = $statuses[$criterion['code']] ?? 'meets';

            return [
                ...$criterion,
                'status' => $status,
                'finding' => 'Hallazgo de prueba.',
                'evidence' => null,
                'recommendation' => null,
                'improvement_example' => 'Ejemplo de mejora.',
                'page' => 1,
            ];
        })->all();
        $report = PedagogicalInstrumentAiReport::query()->create([
            'instrument_id' => $instrument->id,
            'instrument_file_id' => $file->id,
            'status' => 'completed',
            'model' => 'gpt-test',
            'prompt_version' => 'document-review-v1.3.0',
            'report' => [
                'executive_summary' => 'Resumen de prueba.',
                'criteria_assessment' => $criteria,
                'miscellaneous_findings' => $miscellaneous ? [[
                    'category' => 'arithmetic',
                    'severity' => 'important',
                    'title' => 'La suma de puntajes no coincide',
                ]] : [],
            ],
            'requested_by' => $teacher->id,
            'started_at' => Carbon::parse($reviewedAt)->subMinutes(5),
            'finished_at' => Carbon::parse($reviewedAt)->subMinutes(2),
        ]);
        $review = PedagogicalInstrumentReview::query()->create([
            'instrument_id' => $instrument->id,
            'instrument_file_id' => $file->id,
            'decision' => $decision,
            'coordinator_notes' => 'Resolución de prueba.',
            'ai_report_id' => $report->id,
            'share_ai_report' => $decision === 'rectification_requested',
            'reviewed_by' => $teacher->id,
            'reviewed_at' => Carbon::parse($reviewedAt),
        ]);

        app(PedagogicalReportProjectionService::class)->projectReview($review);

        return DB::table('pedagogical_report_snapshots')->where('review_id', $review->id)->firstOrFail();
    }

    private function instrument(User $teacher, School $school, AcademicYear $year, CourseSection $course, ScheduleSubject $subject, string $title): PedagogicalInstrument
    {
        $instrument = PedagogicalInstrument::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'owner_user_id' => $teacher->id,
            'subject_id' => $subject->id,
            'title' => $title,
            'instrument_type' => 'written_test',
            'evaluation_purpose' => 'summative',
            'work_modality' => 'individual',
            'status' => 'uploaded',
            'workflow_status' => 'submitted',
            'submitted_at' => now(),
            'created_by' => $teacher->id,
        ]);
        $instrument->courses()->attach($course->id);

        return $instrument;
    }

    private function context(): array
    {
        $teacher = User::factory()->create(['active' => true]);
        $coordinator = User::factory()->create(['active' => true]);
        $teacher->roles()->sync([Role::query()->where('slug', 'docente')->firstOrFail()->id]);
        $coordinator->roles()->sync([Role::query()->where('slug', 'coordinadora_academica')->firstOrFail()->id]);
        $school = School::query()->create(['rbd' => '78787-8', 'name' => 'Escuela estadísticas', 'timezone' => 'America/Santiago', 'active' => true]);
        $school->users()->attach($teacher->id, ['active' => true, 'role_snapshot' => 'Docente']);
        $school->users()->attach($coordinator->id, ['active' => true, 'role_snapshot' => 'Coordinadora académica']);
        $year = AcademicYear::factory()->create(['year' => 2026, 'name' => '2026', 'starts_at' => '2026-03-01', 'ends_at' => '2026-12-20', 'is_active' => true]);
        $school->academicYears()->attach($year->id, ['rbd_snapshot' => $school->rbd, 'year_snapshot' => 2026, 'timezone_snapshot' => $school->timezone, 'active' => true]);
        $level = EducationLevel::factory()->create(['name' => 'Séptimo básico estadísticas', 'order' => 947, 'type' => 'basica']);
        $otherLevel = EducationLevel::factory()->create(['name' => 'Octavo básico estadísticas', 'order' => 948, 'type' => 'basica']);
        $course = CourseSection::factory()->create(['academic_year_id' => $year->id, 'education_level_id' => $level->id, 'display_name' => '7° Básico A', 'section_name' => 'A', 'active' => true]);
        $otherCourse = CourseSection::factory()->create(['academic_year_id' => $year->id, 'education_level_id' => $otherLevel->id, 'display_name' => '8° Básico B', 'section_name' => 'B', 'active' => true]);
        $subject = ScheduleSubject::query()->create(['name' => 'Lenguaje', 'code' => 'LEN-STAT', 'area' => 'Lenguaje', 'color' => '#176b76', 'active' => true]);

        return [$teacher, $coordinator, $school, $year, $course, $otherCourse, $subject];
    }
}
