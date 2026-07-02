<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaExternalInstitution;
use App\Models\Convivencia\ConvivenciaIdpsResult;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\Department;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ConvivenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_convivencia_seeder_generates_core_records(): void
    {
        $this->seed(ConvivenciaSeeder::class);

        $this->assertGreaterThan(0, ConvivenciaPlan::query()->count());
        $this->assertGreaterThan(0, ConvivenciaProtocol::query()->count());
        $this->assertGreaterThan(0, ConvivenciaCase::query()->count());
        $this->assertGreaterThan(0, ConvivenciaComplaint::query()->count());
        $this->assertGreaterThan(0, ConvivenciaDerivation::query()->count());
        $this->assertGreaterThan(0, ConvivenciaMeasure::query()->count());
        $this->assertGreaterThan(0, ConvivenciaInterview::query()->count());
        $this->assertGreaterThan(0, ConvivenciaDailyLog::query()->count());
        $this->assertGreaterThan(0, ConvivenciaSociogram::query()->count());
        $this->assertGreaterThan(0, ConvivenciaIdpsResult::query()->count());
    }

    public function test_super_admin_can_load_convivencia_dashboard_api(): void
    {
        $this->seedAndActAsSuperAdmin();

        $response = $this->getJson('/api/convivencia/dashboard');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'metrics',
                'charts',
                'recent',
            ]);
    }

    public function test_user_without_convivencia_permission_cannot_access_dashboard_api(): void
    {
        $this->seed(ConvivenciaSeeder::class);

        $user = User::factory()->create([
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/convivencia/dashboard')->assertForbidden();
    }

    public function test_can_create_case(): void
    {
        $user = $this->seedAndActAsSuperAdmin();
        $student = StudentProfile::query()->firstOrFail();

        $response = $this->postJson('/api/convivencia/cases', [
            'student_profile_id' => $student->id,
            'classification_item_id' => $this->catalogId('classification'),
            'criticality_item_id' => $this->catalogId('criticality'),
            'responsible_user_id' => $user->id,
            'responsible_staff_id' => $user->staff_id,
            'opened_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'origin' => 'observacion',
            'initial_report' => 'Se registra un caso de convivencia para validación automatizada.',
            'people' => [
                [
                    'person_type' => 'estudiante',
                    'role_type' => 'afectado',
                    'full_name' => $student->registered_name_resolved,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.origin', 'observacion');

        $this->assertDatabaseHas('convivencia_cases', [
            'initial_report' => 'Se registra un caso de convivencia para validación automatizada.',
        ]);
    }

    public function test_can_create_public_complaint(): void
    {
        $this->seed(ConvivenciaSeeder::class);

        $student = StudentProfile::query()->firstOrFail();

        $response = $this->postJson('/api/convivencia/public/complaints', [
            'affected_student_id' => $student->id,
            'complainant_type' => 'apoderado',
            'complainant_name' => 'Apoderado de prueba',
            'contact_email' => 'apoderado@example.test',
            'report_text' => 'Relato de denuncia pública de prueba con antecedentes suficientes.',
            'truth_declaration_accepted' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'folio',
                'data' => ['folio', 'status', 'received_at'],
            ]);

        $folio = $response->json('folio');

        $this->assertNotEmpty($folio);
        $this->getJson("/api/convivencia/public/complaints/{$folio}")
            ->assertOk()
            ->assertJsonPath('data.folio', $folio);
    }

    public function test_can_convert_complaint_to_case(): void
    {
        $user = $this->seedAndActAsSuperAdmin();
        $complaint = ConvivenciaComplaint::query()->whereNull('case_id')->firstOrFail();

        $response = $this->postJson("/api/convivencia/complaints/{$complaint->id}/convert-to-case", [
            'classification_item_id' => $this->catalogId('classification'),
            'criticality_item_id' => $this->catalogId('criticality'),
            'responsible_user_id' => $user->id,
            'responsible_staff_id' => $user->staff_id,
        ]);

        $response->assertOk();

        $complaint->refresh();

        $this->assertNotNull($complaint->case_id);
        $this->assertSame('derivada_a_caso', $complaint->status);
        $this->assertDatabaseHas('convivencia_cases', [
            'id' => $complaint->case_id,
        ]);
    }

    public function test_can_activate_protocol(): void
    {
        $this->seedAndActAsSuperAdmin();

        $protocol = ConvivenciaProtocol::query()->firstOrFail();
        $case = ConvivenciaCase::query()->firstOrFail();

        $response = $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol->id,
            'case_id' => $case->id,
            'status' => 'activo',
            'actions_taken' => 'Se activa protocolo desde test automatizado.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.protocol.id', $protocol->id);

        $this->assertDatabaseHas('convivencia_protocol_activations', [
            'protocol_id' => $protocol->id,
            'case_id' => $case->id,
        ]);
    }

    public function test_can_create_measure(): void
    {
        $user = $this->seedAndActAsSuperAdmin();
        $case = ConvivenciaCase::query()->firstOrFail();

        $response = $this->postJson('/api/convivencia/measures', [
            'case_id' => $case->id,
            'student_profile_id' => $case->student_profile_id,
            'course_section_id' => $case->course_section_id,
            'measure_type_item_id' => $this->catalogId('measure_type'),
            'responsible_user_id' => $user->id,
            'responsible_staff_id' => $user->staff_id,
            'description' => 'Medida formativa generada desde prueba automatizada.',
            'training_objective' => 'Promover la reparación del vínculo y la reflexión.',
            'assigned_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'due_at' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'),
            'status' => 'asignada',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('convivencia_measures', [
            'description' => 'Medida formativa generada desde prueba automatizada.',
        ]);
    }

    public function test_can_create_interview(): void
    {
        $user = $this->seedAndActAsSuperAdmin();
        $case = ConvivenciaCase::query()->firstOrFail();
        $student = StudentProfile::query()->findOrFail($case->student_profile_id);

        $response = $this->postJson('/api/convivencia/interviews', [
            'case_id' => $case->id,
            'student_profile_id' => $student->id,
            'course_section_id' => $case->course_section_id,
            'interview_type_item_id' => $this->catalogId('interview_type'),
            'responsible_user_id' => $user->id,
            'responsible_staff_id' => $user->staff_id,
            'interview_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'motive' => 'Entrevista de seguimiento de caso.',
            'follow_up_status' => 'pendiente',
            'participants' => [
                [
                    'participant_type' => 'estudiante',
                    'participant_role' => 'entrevistado',
                    'full_name' => $student->registered_name_resolved,
                    'student_profile_id' => $student->id,
                ],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('convivencia_interviews', [
            'motive' => 'Entrevista de seguimiento de caso.',
        ]);
    }

    public function test_can_create_internal_derivation(): void
    {
        $user = $this->seedAndActAsSuperAdmin();
        $case = ConvivenciaCase::query()->firstOrFail();
        $department = Department::query()->firstOrFail();

        $response = $this->postJson('/api/convivencia/derivations', [
            'case_id' => $case->id,
            'student_profile_id' => $case->student_profile_id,
            'course_section_id' => $case->course_section_id,
            'scope' => 'internal',
            'status' => 'ingresada',
            'priority_level' => 'media',
            'confidentiality_level' => 'reservada',
            'destination_department_id' => $department->id,
            'responsible_user_id' => $user->id,
            'derived_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'motive' => 'Derivación interna creada desde prueba automatizada.',
            'narrative' => 'Se solicita intervención del área correspondiente.',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('convivencia_derivations', [
            'scope' => 'internal',
            'motive' => 'Derivación interna creada desde prueba automatizada.',
        ]);
    }

    public function test_can_create_external_derivation(): void
    {
        $user = $this->seedAndActAsSuperAdmin();
        $case = ConvivenciaCase::query()->firstOrFail();
        $institution = ConvivenciaExternalInstitution::query()->firstOrFail();

        $response = $this->postJson('/api/convivencia/derivations', [
            'case_id' => $case->id,
            'student_profile_id' => $case->student_profile_id,
            'course_section_id' => $case->course_section_id,
            'scope' => 'external',
            'status' => 'ingresada',
            'priority_level' => 'alta',
            'confidentiality_level' => 'alta',
            'external_institution_id' => $institution->id,
            'responsible_user_id' => $user->id,
            'derived_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'motive' => 'Derivación externa creada desde prueba automatizada.',
            'narrative' => 'Se remiten antecedentes a red externa.',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('convivencia_derivations', [
            'scope' => 'external',
            'motive' => 'Derivación externa creada desde prueba automatizada.',
        ]);
    }

    private function seedAndActAsSuperAdmin(): User
    {
        $this->seed(ConvivenciaSeeder::class);

        $user = User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->firstOrFail();

        Sanctum::actingAs($user);

        return $user;
    }

    private function catalogId(string $group): int
    {
        return (int) ConvivenciaCatalogItem::query()
            ->where('group', $group)
            ->orderBy('sort_order')
            ->value('id');
    }
}
