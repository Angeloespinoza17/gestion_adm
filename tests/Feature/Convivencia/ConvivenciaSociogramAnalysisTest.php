<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\StudentEnrollment;
use App\Models\User;
use Database\Seeders\ConvivenciaSeeder;
use Database\Seeders\ConvivenciaSociogramDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaSociogramAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_idempotent_and_builds_a_complete_graph_snapshot(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $before = ConvivenciaSociogram::query()->count();

        $this->seed(ConvivenciaSociogramDemoSeeder::class);
        $this->assertDatabaseCount('convivencia_sociograms', $before + 1);

        $sociogram = ConvivenciaSociogram::query()
            ->where('title', 'DEMO · Sociograma gráfico del curso')
            ->firstOrFail();
        $analysis = $sociogram->result_summary;

        $this->assertSame(2, $analysis['schema_version']);
        $this->assertGreaterThanOrEqual(3, $analysis['metrics']['students_total']);
        $this->assertGreaterThan(0, $analysis['metrics']['positive_links']);
        $this->assertGreaterThan(0, $analysis['metrics']['reciprocal_pairs']);
        $this->assertGreaterThan(0, $analysis['metrics']['without_positive_nominations']);
        $this->assertCount(3, $analysis['question_breakdown']);
        $this->assertCount($analysis['metrics']['students_total'], $analysis['graph']['nodes']);
        $this->assertNotEmpty($analysis['graph']['edges']);

        $fingerprint = hash('sha256', json_encode([
            $sociogram->only(['id', 'updated_at']),
            $sociogram->questions()->orderBy('id')->pluck('id')->all(),
            $sociogram->answers()->orderBy('id')->pluck('id')->all(),
            $analysis,
        ]));
        $this->seed(ConvivenciaSociogramDemoSeeder::class);
        $sociogram->refresh();
        $this->assertDatabaseCount('convivencia_sociograms', $before + 1);
        $this->assertSame($fingerprint, hash('sha256', json_encode([
            $sociogram->only(['id', 'updated_at']),
            $sociogram->questions()->orderBy('id')->pluck('id')->all(),
            $sociogram->answers()->orderBy('id')->pluck('id')->all(),
            $sociogram->result_summary,
        ])));
    }

    public function test_authorized_show_returns_graph_without_student_identifiers(): void
    {
        $user = $this->seedAndActAsSuperAdmin();
        $this->seed(ConvivenciaSociogramDemoSeeder::class);
        $sociogram = ConvivenciaSociogram::query()->where('title', 'DEMO · Sociograma gráfico del curso')->firstOrFail();

        $response = $this->getJson("/api/convivencia/sociograms/{$sociogram->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.analysis.schema_version', 2)
            ->assertJsonStructure([
                'data' => [
                    'analysis' => [
                        'metrics' => ['students_total', 'response_rate', 'reciprocity_rate', 'positive_density'],
                        'graph' => ['nodes', 'edges'],
                        'rankings',
                        'question_breakdown',
                        'methodology',
                    ],
                ],
            ]);
        $this->assertStringNotContainsString('"rut"', $response->getContent());
        $this->assertSame($user->id, $sociogram->created_by);
    }

    public function test_api_rejects_self_selections_wrong_types_and_students_outside_the_course(): void
    {
        $this->seedAndActAsSuperAdmin();
        $this->seed(ConvivenciaSociogramDemoSeeder::class);
        $sociogram = ConvivenciaSociogram::query()->where('title', 'DEMO · Sociograma gráfico del curso')->firstOrFail();
        $question = $sociogram->questions()->orderBy('id')->firstOrFail();
        $studentId = (int) StudentEnrollment::query()
            ->where('course_section_id', $sociogram->course_section_id)
            ->value('student_profile_id');
        $outsideStudentId = (int) StudentEnrollment::query()
            ->where('course_section_id', '!=', $sociogram->course_section_id)
            ->value('student_profile_id');

        $base = [
            'course_section_id' => $sociogram->course_section_id,
            'title' => $sociogram->title,
            'applied_on' => $sociogram->applied_on->format('Y-m-d'),
            'status' => $sociogram->status,
            'confidentiality_level' => $sociogram->confidentiality_level,
        ];

        $this->putJson("/api/convivencia/sociograms/{$sociogram->id}", $base + [
            'answers' => [[
                'question_order' => 1,
                'respondent_student_id' => $studentId,
                'selected_student_id' => $studentId,
                'selection_type' => $question->selection_type,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('answers.0.selected_student_id');

        $this->putJson("/api/convivencia/sociograms/{$sociogram->id}", $base + [
            'answers' => [[
                'question_order' => 1,
                'respondent_student_id' => $studentId,
                'selected_student_id' => $outsideStudentId,
                'selection_type' => 'negativa',
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'answers.0.selected_student_id',
            'answers.0.selection_type',
        ]);
    }

    private function seedAndActAsSuperAdmin(): User
    {
        $this->seed(ConvivenciaSeeder::class);
        $user = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }
}
