<?php

namespace Tests\Feature\Convivencia;

use App\Models\ApoyoProfesional\ApoyoProfesionalProfile;
use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\ConvivenciaCaseCatalogSeeder;
use Database\Seeders\ConvivenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaCaseFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_catalog_seeder_is_complete_idempotent_and_does_not_create_operational_data(): void
    {
        ConvivenciaCatalogItem::query()->create([
            'group' => 'custom_group',
            'code' => 'custom_item',
            'name' => 'Ítem institucional ajeno al instalador',
            'active' => true,
        ]);

        $this->seed(ConvivenciaCaseCatalogSeeder::class);

        $this->assertDatabaseCount('convivencia_cases', 0);
        $this->assertSame(5, ConvivenciaCatalogItem::query()->where('group', 'case_type')->count());
        $this->assertSame(12, ConvivenciaCatalogItem::query()->where('group', 'classification')->count());
        $this->assertSame(42, ConvivenciaCatalogItem::query()->where('group', 'subclassification')->count());
        $this->assertSame(4, ConvivenciaCatalogItem::query()->where('group', 'criticality')->count());
        $this->assertDatabaseHas('convivencia_catalog_items', [
            'group' => 'custom_group',
            'code' => 'custom_item',
            'name' => 'Ítem institucional ajeno al instalador',
        ]);

        $subclassificationsWithoutParent = ConvivenciaCatalogItem::query()
            ->where('group', 'subclassification')
            ->whereNull('parent_id')
            ->count();
        $this->assertSame(0, $subclassificationsWithoutParent);

        $signature = ConvivenciaCatalogItem::query()
            ->whereIn('group', ['case_type', 'classification', 'subclassification', 'criticality'])
            ->orderBy('group')
            ->orderBy('code')
            ->get(['id', 'parent_id', 'group', 'code', 'name', 'updated_at'])
            ->toJson();

        $this->travel(2)->minutes();
        $this->seed(ConvivenciaCaseCatalogSeeder::class);

        $this->assertSame(
            $signature,
            ConvivenciaCatalogItem::query()
                ->whereIn('group', ['case_type', 'classification', 'subclassification', 'criticality'])
                ->orderBy('group')
                ->orderBy('code')
                ->get(['id', 'parent_id', 'group', 'code', 'name', 'updated_at'])
                ->toJson(),
        );
    }

    public function test_case_api_exposes_catalogs_and_rejects_cross_group_or_unrelated_subclassification_ids(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $this->seed(ConvivenciaCaseCatalogSeeder::class);

        $user = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        Sanctum::actingAs($user);

        $professionalUser = User::query()->whereNotNull('staff_id')->whereKeyNot($user->id)->firstOrFail();
        $professionalProfile = ApoyoProfesionalProfile::query()->create([
            'user_id' => $professionalUser->id,
            'staff_id' => $professionalUser->staff_id,
            'area_slug' => 'psicologia',
            'area_name' => 'Psicología',
            'professional_role_slug' => 'psicologo',
            'professional_role_name' => 'Psicóloga educacional',
            'can_receive_derivations' => true,
            'can_manage_confidential_cases' => true,
            'active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $student = StudentProfile::query()->firstOrFail();

        $classification = $this->item('classification', 'maltrato_escolar');
        $wrongClassification = $this->item('classification', 'conflicto_convivencia');
        $subclassification = $this->item('subclassification', 'agresion_fisica');
        $criticality = $this->item('criticality', 'alta');
        $caseType = $this->item('case_type', 'caso_convivencia');

        $catalogResponse = $this->getJson('/api/convivencia/catalogs')->assertOk();
        $catalogResponse->assertJsonPath('current_user_id', $user->id);
        $this->assertGreaterThanOrEqual(12, count($catalogResponse->json('catalogs.classification')));
        $this->assertCount(4, $catalogResponse->json('catalogs.criticality'));
        $catalogResponse->assertJsonFragment([
            'profile_id' => $professionalProfile->id,
            'full_name' => $professionalUser->staff->full_name,
            'area_name' => 'Psicología',
            'professional_role_name' => 'Psicóloga educacional',
        ]);
        $this->assertContains(
            $classification->id,
            array_column($catalogResponse->json('catalogs.subclassification'), 'parent_id'),
        );

        $basePayload = [
            'academic_year_id' => $catalogResponse->json('active_academic_year_id'),
            'case_type_item_id' => $caseType->id,
            'classification_item_id' => $classification->id,
            'subclassification_item_id' => $subclassification->id,
            'criticality_item_id' => $criticality->id,
            'responsible_user_id' => $professionalUser->id,
            'opened_at' => now()->format('Y-m-d H:i:s'),
            'origin' => 'observacion',
            'initial_report' => 'Relato objetivo y suficiente para probar la apertura guiada del caso.',
            'people' => [
                [
                    'student_profile_id' => $student->id,
                    'person_type' => 'estudiante',
                    'role_type' => 'afectado',
                    'full_name' => $student->registered_name_resolved,
                    'identifier' => $student->rut,
                ],
                [
                    'user_id' => $professionalUser->id,
                    'staff_id' => $professionalUser->staff_id,
                    'person_type' => 'funcionario',
                    'role_type' => 'profesional_apoyo',
                    'full_name' => $professionalUser->staff->full_name,
                    'relationship_label' => 'Psicología · Psicóloga educacional',
                ],
            ],
        ];

        $this->postJson('/api/convivencia/cases', [
            ...$basePayload,
            'people' => [$basePayload['people'][0], $basePayload['people'][0]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('people.1.student_profile_id')
            ->assertJsonFragment(['Esta alumna ya está vinculada al caso.']);

        $this->postJson('/api/convivencia/cases', [
            ...$basePayload,
            'people' => [$basePayload['people'][1], $basePayload['people'][1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('people.1.staff_id')
            ->assertJsonFragment(['Este profesional ya forma parte del equipo de apoyo del caso.']);

        $this->postJson('/api/convivencia/cases', [
            ...$basePayload,
            'classification_item_id' => $criticality->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('classification_item_id');

        $this->postJson('/api/convivencia/cases', [
            ...$basePayload,
            'classification_item_id' => $wrongClassification->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('subclassification_item_id')
            ->assertJsonPath(
                'errors.subclassification_item_id.0',
                'La subclasificación no pertenece a la clasificación seleccionada.',
            );

        $this->postJson('/api/convivencia/cases', $basePayload)
            ->assertCreated()
            ->assertJsonPath('data.classification_label', 'Maltrato o violencia escolar')
            ->assertJsonPath('data.subclassification_label', 'Agresión física')
            ->assertJsonPath('data.criticality_label', 'Alta');

        $this->assertSame(1, ConvivenciaCase::query()
            ->where('initial_report', $basePayload['initial_report'])
            ->count());
        $case = ConvivenciaCase::query()->where('initial_report', $basePayload['initial_report'])->firstOrFail();
        $this->assertDatabaseHas('convivencia_case_people', [
            'case_id' => $case->id,
            'student_profile_id' => $student->id,
            'role_type' => 'afectado',
        ]);
        $this->assertDatabaseHas('convivencia_case_people', [
            'case_id' => $case->id,
            'staff_id' => $professionalUser->staff_id,
            'role_type' => 'profesional_apoyo',
        ]);
        $this->assertSame($professionalUser->id, $case->responsible_user_id);
        $this->assertSame($professionalUser->staff_id, $case->responsible_staff_id);
    }

    private function item(string $group, string $code): ConvivenciaCatalogItem
    {
        return ConvivenciaCatalogItem::query()
            ->where('group', $group)
            ->where('code', $code)
            ->firstOrFail();
    }
}
