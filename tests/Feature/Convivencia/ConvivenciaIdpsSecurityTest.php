<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaIdpsDimension;
use App\Models\Convivencia\ConvivenciaIdpsInstrument;
use App\Models\Convivencia\ConvivenciaIdpsPeriod;
use App\Models\Convivencia\ConvivenciaIdpsResult;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ConvivenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaIdpsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_results_reject_inactive_dimensions_and_instruments(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $user = $this->actAsSuperAdmin();
        $resultCount = ConvivenciaIdpsResult::query()->count();
        $period = $this->period($user);
        $inactiveDimension = $this->dimension($user, 'inactive-new-dimension', false);
        $instrumentOnInactiveDimension = $this->instrument($user, $inactiveDimension, 'Instrumento de dimensión inactiva', true);

        $this->postJson('/api/convivencia/idps/results', $this->payload(
            $period,
            $inactiveDimension,
            $instrumentOnInactiveDimension,
        ))->assertUnprocessable()
            ->assertJsonValidationErrors('dimension_id')
            ->assertJsonPath('errors.dimension_id.0', 'La dimensión seleccionada está inactiva o no existe.');

        $activeDimension = $this->dimension($user, 'active-new-dimension', true);
        $inactiveInstrument = $this->instrument($user, $activeDimension, 'Instrumento inactivo', false);
        $this->postJson('/api/convivencia/idps/results', $this->payload(
            $period,
            $activeDimension,
            $inactiveInstrument,
        ))->assertUnprocessable()
            ->assertJsonValidationErrors('instrument_id')
            ->assertJsonPath('errors.instrument_id.0', 'El instrumento seleccionado está inactivo o no pertenece a la dimensión indicada.');

        $this->assertDatabaseCount('convivencia_idps_results', $resultCount);
    }

    public function test_historical_result_can_keep_its_inactive_pair_but_cannot_change_to_another_inactive_pair(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $user = $this->actAsSuperAdmin();
        $period = $this->period($user);
        $historicalDimension = $this->dimension($user, 'inactive-historical-dimension', false);
        $historicalInstrument = $this->instrument($user, $historicalDimension, 'Instrumento histórico', false);
        $result = $this->createResult($user, $period, $historicalDimension, $historicalInstrument, false);

        $this->putJson('/api/convivencia/idps/results/'.$result->id, [
            ...$this->payload($period, $historicalDimension, $historicalInstrument),
            'percentage' => 74,
        ])->assertOk()
            ->assertJsonPath('data.dimension_id', $historicalDimension->id)
            ->assertJsonPath('data.instrument_id', $historicalInstrument->id)
            ->assertJsonPath('data.percentage', '74.00');

        $otherDimension = $this->dimension($user, 'other-inactive-dimension', false);
        $otherInstrument = $this->instrument($user, $otherDimension, 'Otro instrumento inactivo', false);
        $this->putJson('/api/convivencia/idps/results/'.$result->id, $this->payload(
            $period,
            $otherDimension,
            $otherInstrument,
        ))->assertUnprocessable()
            ->assertJsonValidationErrors(['dimension_id', 'instrument_id']);

        $result->refresh();
        $this->assertSame($historicalDimension->id, $result->dimension_id);
        $this->assertSame($historicalInstrument->id, $result->instrument_id);
        $this->assertSame('74.00', $result->percentage);
    }

    public function test_manager_cannot_update_another_users_sensitive_result_without_sensitive_access(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $owner = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        $period = $this->period($owner);
        $dimension = $this->dimension($owner, 'sensitive-update-dimension', true);
        $instrument = $this->instrument($owner, $dimension, 'Instrumento sensible', true);
        $result = $this->createResult($owner, $period, $dimension, $instrument, true);

        $manager = $this->userWithPermissions(['gestionar_plan_convivencia'], 'gestor_idps_sin_sensible');
        Sanctum::actingAs($manager);
        $this->putJson('/api/convivencia/idps/results/'.$result->id, [
            ...$this->payload($period, $dimension, $instrument),
            'percentage' => 99,
        ])->assertForbidden();
        $this->assertSame('61.00', $result->fresh()->percentage);

        $result->forceFill(['created_by' => $manager->id, 'updated_by' => $manager->id])->save();
        $this->putJson('/api/convivencia/idps/results/'.$result->id, [
            ...$this->payload($period, $dimension, $instrument),
            'percentage' => 72,
            'is_sensitive' => true,
        ])->assertOk();
        $this->assertSame('72.00', $result->fresh()->percentage);

        $sensitiveManager = $this->userWithPermissions([
            'gestionar_plan_convivencia',
            'ver_casos_sensibles_convivencia',
        ], 'gestor_idps_con_sensible');
        $result->forceFill(['created_by' => $owner->id, 'updated_by' => $owner->id])->save();
        Sanctum::actingAs($sensitiveManager);
        $this->putJson('/api/convivencia/idps/results/'.$result->id, [
            ...$this->payload($period, $dimension, $instrument),
            'percentage' => 83,
            'is_sensitive' => true,
        ])->assertOk();
        $this->assertSame('83.00', $result->fresh()->percentage);
    }

    public function test_related_plan_must_be_visible_to_the_user_saving_the_result(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $owner = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        $period = $this->period($owner);
        $dimension = $this->dimension($owner, 'private-plan-dimension', true);
        $instrument = $this->instrument($owner, $dimension, 'Instrumento para plan privado', true);
        $privatePlan = ConvivenciaPlan::query()->create([
            'name' => 'Plan sensible ajeno',
            'general_objective' => 'Objetivo reservado de prueba.',
            'status' => 'vigente',
            'is_sensitive' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $resultCount = ConvivenciaIdpsResult::query()->count();
        $payload = [
            ...$this->payload($period, $dimension, $instrument),
            'related_plan_id' => $privatePlan->id,
        ];

        $settingsManager = $this->userWithPermissions(
            ['administrar_configuraciones_convivencia'],
            'configuracion_idps_sin_plan',
        );
        Sanctum::actingAs($settingsManager);
        $this->postJson('/api/convivencia/idps/results', $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.related_plan_id.0',
                'El plan seleccionado no está disponible para tu nivel de acceso.',
            );
        $this->assertDatabaseCount('convivencia_idps_results', $resultCount);

        Sanctum::actingAs($owner);
        $this->postJson('/api/convivencia/idps/results', $payload)
            ->assertCreated()
            ->assertJsonPath('data.related_plan_id', $privatePlan->id);
    }

    private function payload(
        ConvivenciaIdpsPeriod $period,
        ConvivenciaIdpsDimension $dimension,
        ConvivenciaIdpsInstrument $instrument,
    ): array {
        return [
            'period_id' => $period->id,
            'dimension_id' => $dimension->id,
            'instrument_id' => $instrument->id,
            'academic_year_id' => null,
            'course_section_id' => null,
            'education_level_id' => null,
            'related_plan_id' => null,
            'result_scope' => 'establecimiento',
            'reference_label' => 'Resultado IDPS de prueba',
            'score' => 3.05,
            'percentage' => 61,
            'sample_size' => 28,
            'qualitative_observations' => 'Observación de prueba controlada.',
            'improvement_actions' => 'Acción de mejora controlada.',
            'is_sensitive' => false,
        ];
    }

    private function period(User $user): ConvivenciaIdpsPeriod
    {
        return ConvivenciaIdpsPeriod::query()->create([
            'name' => 'Período de seguridad IDPS '.uniqid(),
            'status' => 'abierto',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function dimension(User $user, string $code, bool $active): ConvivenciaIdpsDimension
    {
        return ConvivenciaIdpsDimension::query()->create([
            'code' => $code,
            'name' => str_replace('-', ' ', ucfirst($code)),
            'active' => $active,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function instrument(
        User $user,
        ConvivenciaIdpsDimension $dimension,
        string $name,
        bool $active,
    ): ConvivenciaIdpsInstrument {
        return ConvivenciaIdpsInstrument::query()->create([
            'dimension_id' => $dimension->id,
            'name' => $name,
            'response_type' => 'escala',
            'active' => $active,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function createResult(
        User $user,
        ConvivenciaIdpsPeriod $period,
        ConvivenciaIdpsDimension $dimension,
        ConvivenciaIdpsInstrument $instrument,
        bool $sensitive,
    ): ConvivenciaIdpsResult {
        return ConvivenciaIdpsResult::query()->create([
            ...$this->payload($period, $dimension, $instrument),
            'is_sensitive' => $sensitive,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function actAsSuperAdmin(): User
    {
        $user = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function userWithPermissions(array $slugs, string $roleSlug): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => str_replace('_', ' ', ucfirst($roleSlug)),
            'slug' => $roleSlug,
            'active' => true,
        ]);
        $permissions = Permission::query()->whereIn('slug', $slugs)->pluck('id');
        $this->assertCount(count($slugs), $permissions);
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);

        return $user->fresh();
    }
}
