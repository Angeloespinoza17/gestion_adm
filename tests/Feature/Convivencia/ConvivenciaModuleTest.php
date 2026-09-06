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
use App\Models\Permission;
use App\Models\Role;
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

    public function test_course_report_filters_indirect_records_by_academic_year_without_invalid_columns(): void
    {
        $this->seedAndActAsSuperAdmin();
        $academicYearId = ConvivenciaCase::query()->whereNotNull('academic_year_id')->value('academic_year_id');

        $this->getJson("/api/convivencia/reports/course?academic_year_id={$academicYearId}")
            ->assertOk()
            ->assertJsonStructure([
                'summary' => ['open_cases', 'interviews', 'measures', 'alerts'],
                'lists' => ['cases', 'daily_logs', 'derivations', 'interviews', 'measures'],
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

    public function test_daily_log_manager_cannot_create_cases_or_derivations_without_destination_permissions(): void
    {
        $this->seed(ConvivenciaSeeder::class);

        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Gestor exclusivo de bitácora',
            'slug' => 'gestor_exclusivo_bitacora_test',
            'active' => true,
        ]);
        $role->permissions()->attach(
            Permission::query()->where('slug', 'gestionar_bitacora_inspectoria_convivencia')->value('id')
        );
        $user->roles()->attach($role);

        $dailyLog = ConvivenciaDailyLog::query()->firstOrFail();
        $dailyLog->forceFill([
            'inspector_user_id' => $user->id,
            'is_sensitive' => false,
        ])->save();
        $caseCount = ConvivenciaCase::query()->count();
        $derivationCount = ConvivenciaDerivation::query()->count();

        Sanctum::actingAs($user->fresh());

        $this->postJson("/api/convivencia/daily-logs/{$dailyLog->id}/convert-to-case", [])
            ->assertForbidden();
        $this->postJson("/api/convivencia/daily-logs/{$dailyLog->id}/convert-to-derivation", [
            'scope' => 'internal',
        ])->assertForbidden();

        $this->assertDatabaseCount('convivencia_cases', $caseCount);
        $this->assertDatabaseCount('convivencia_derivations', $derivationCount);
    }

    public function test_complaint_manager_needs_case_creation_permission_to_convert_a_complaint(): void
    {
        $this->seed(ConvivenciaSeeder::class);

        $permissionIds = Permission::query()
            ->whereIn('slug', [
                'gestionar_denuncias_convivencia',
                'crear_casos_convivencia',
            ])
            ->pluck('id', 'slug');

        $complaintManager = User::factory()->create(['active' => true]);
        $complaintRole = Role::query()->create([
            'name' => 'Gestor exclusivo de denuncias',
            'slug' => 'gestor_exclusivo_denuncias_test',
            'active' => true,
        ]);
        $complaintRole->permissions()->attach($permissionIds['gestionar_denuncias_convivencia']);
        $complaintManager->roles()->attach($complaintRole);

        $complaint = ConvivenciaComplaint::query()->whereNull('case_id')->firstOrFail();
        $complaint->forceFill([
            'responsible_user_id' => $complaintManager->id,
            'is_sensitive' => false,
        ])->save();

        $payload = [
            'classification_item_id' => $this->catalogId('classification'),
            'criticality_item_id' => $this->catalogId('criticality'),
            'responsible_user_id' => $complaintManager->id,
        ];
        $caseCount = ConvivenciaCase::query()->count();

        Sanctum::actingAs($complaintManager->fresh());

        $this->postJson("/api/convivencia/complaints/{$complaint->id}/convert-to-case", $payload)
            ->assertForbidden();
        $this->assertDatabaseCount('convivencia_cases', $caseCount);
        $this->assertNull($complaint->fresh()->case_id);

        $authorizedManager = User::factory()->create(['active' => true]);
        $authorizedRole = Role::query()->create([
            'name' => 'Gestor de denuncias con apertura de casos',
            'slug' => 'gestor_denuncias_crear_casos_test',
            'active' => true,
        ]);
        $authorizedRole->permissions()->attach([
            $permissionIds['gestionar_denuncias_convivencia'],
            $permissionIds['crear_casos_convivencia'],
        ]);
        $authorizedManager->roles()->attach($authorizedRole);

        $authorizedComplaint = ConvivenciaComplaint::query()
            ->whereNull('case_id')
            ->whereKeyNot($complaint->id)
            ->firstOrFail();
        $authorizedComplaint->forceFill([
            'responsible_user_id' => $authorizedManager->id,
            'is_sensitive' => false,
        ])->save();

        Sanctum::actingAs($authorizedManager->fresh());

        $this->postJson("/api/convivencia/complaints/{$authorizedComplaint->id}/convert-to-case", [
            ...$payload,
            'responsible_user_id' => $authorizedManager->id,
        ])->assertOk();

        $this->assertDatabaseCount('convivencia_cases', $caseCount + 1);
        $this->assertNotNull($authorizedComplaint->fresh()->case_id);
    }

    public function test_anonymous_complaint_does_not_persist_identity_or_contact_data(): void
    {
        $this->seedAndActAsSuperAdmin();

        $response = $this->postJson('/api/convivencia/complaints', [
            'complainant_type' => 'anonimo',
            'complainant_name' => 'Identidad que debe descartarse',
            'contact_email' => 'confidencial@example.test',
            'contact_phone' => '+56 9 1111 2222',
            'report_text' => 'Relato anónimo con antecedentes suficientes para registrar la denuncia.',
            'status' => 'recibida',
            'is_anonymous' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.is_anonymous', true)
            ->assertJsonPath('data.complainant_name', null)
            ->assertJsonPath('data.contact_email', null)
            ->assertJsonPath('data.contact_phone', null);

        $this->assertDatabaseHas('convivencia_complaints', [
            'id' => $response->json('data.id'),
            'is_anonymous' => true,
            'complainant_name' => null,
            'contact_email' => null,
            'contact_phone' => null,
        ]);
    }

    public function test_derivation_manager_is_restricted_to_its_authorized_scope(): void
    {
        $this->seed(ConvivenciaSeeder::class);

        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Gestor exclusivo de derivaciones externas',
            'slug' => 'gestor_derivaciones_externas_test',
            'active' => true,
        ]);
        $role->permissions()->attach(
            Permission::query()->where('slug', 'gestionar_derivaciones_externas_convivencia')->value('id')
        );
        $user->roles()->attach($role);

        $internal = ConvivenciaDerivation::query()->where('scope', 'internal')->firstOrFail();
        $external = ConvivenciaDerivation::query()->where('scope', 'external')->firstOrFail();
        ConvivenciaDerivation::query()->update(['is_sensitive' => false]);

        Sanctum::actingAs($user->fresh());

        $list = $this->getJson('/api/convivencia/derivations?per_page=50')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($list);
        $this->assertTrue(collect($list)->every(fn (array $item): bool => $item['scope'] === 'external'));
        $this->getJson("/api/convivencia/derivations/{$external->id}")->assertOk();
        $this->getJson("/api/convivencia/derivations/{$internal->id}")->assertForbidden();
        $this->deleteJson("/api/convivencia/derivations/{$internal->id}")->assertForbidden();
        $this->postJson("/api/convivencia/derivations/{$external->id}/convert-to-case", [
            'classification_item_id' => $this->catalogId('classification'),
            'criticality_item_id' => $this->catalogId('criticality'),
            'responsible_user_id' => $user->id,
        ])->assertForbidden();

        $payload = [
            'scope' => 'internal',
            'status' => 'ingresada',
            'priority_level' => 'media',
            'confidentiality_level' => 'reservada',
            'destination_label' => 'Equipo interno',
            'derived_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'motive' => 'Solicitud que no corresponde al alcance autorizado.',
        ];

        $this->postJson('/api/convivencia/derivations', $payload)->assertForbidden();
        $this->postJson('/api/convivencia/derivations', [
            ...$payload,
            'scope' => 'external',
            'destination_label' => 'Red comunal externa',
        ])->assertCreated();

        $this->putJson("/api/convivencia/derivations/{$external->id}", [
            ...$payload,
            'scope' => 'internal',
        ])->assertForbidden();
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

        $caseCount = ConvivenciaCase::query()->count();
        $this->postJson("/api/convivencia/complaints/{$complaint->id}/convert-to-case", [
            'classification_item_id' => $this->catalogId('classification'),
            'criticality_item_id' => $this->catalogId('criticality'),
            'responsible_user_id' => $user->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('record');
        $this->assertDatabaseCount('convivencia_cases', $caseCount);
    }

    public function test_can_convert_derivation_to_case_with_traceability_and_without_duplicates(): void
    {
        $user = $this->seedAndActAsSuperAdmin();
        $source = ConvivenciaDerivation::query()->with('student')->firstOrFail();
        $derivation = $source->replicate();
        $derivation->case_id = null;
        $derivation->motive = 'Situación derivada que requiere abrir un expediente formal.';
        $derivation->narrative = 'Antecedentes técnicos comunicados por la red de apoyo.';
        $derivation->suggested_actions = 'Mantener acompañamiento y seguimiento semanal.';
        $derivation->response_text = 'La red confirma recepción de los antecedentes.';
        $derivation->follow_up_notes = 'Revisar avances con el equipo de convivencia.';
        $derivation->save();

        $response = $this->postJson("/api/convivencia/derivations/{$derivation->id}/convert-to-case", [
            'classification_item_id' => $this->catalogId('classification'),
            'criticality_item_id' => $this->catalogId('criticality'),
            'responsible_user_id' => $user->id,
            'is_sensitive' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.origin', 'derivacion')
            ->assertJsonPath('data.sourceable_id', $derivation->id)
            ->assertJsonPath('data.initial_report', "Situación derivada que requiere abrir un expediente formal.\n\nAntecedentes técnicos comunicados por la red de apoyo.");

        $caseId = $response->json('data.id');
        $derivation->refresh();
        $this->assertSame($caseId, $derivation->case_id);
        $this->assertDatabaseHas('convivencia_cases', [
            'id' => $caseId,
            'sourceable_type' => $derivation->getMorphClass(),
            'sourceable_id' => $derivation->id,
            'student_profile_id' => $derivation->student_profile_id,
            'origin' => 'derivacion',
            'is_sensitive' => true,
        ]);

        $caseCount = ConvivenciaCase::query()->count();
        $this->postJson("/api/convivencia/derivations/{$derivation->id}/convert-to-case", [
            'classification_item_id' => $this->catalogId('classification'),
            'criticality_item_id' => $this->catalogId('criticality'),
            'responsible_user_id' => $user->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('record');
        $this->assertDatabaseCount('convivencia_cases', $caseCount);
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
