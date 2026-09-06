<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaIdpsDimension;
use App\Models\Convivencia\ConvivenciaIdpsInstrument;
use App\Models\Convivencia\ConvivenciaIdpsPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaIdpsResultValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_result_accepts_an_instrument_from_the_selected_dimension(): void
    {
        $this->actAsSuperAdmin();
        [$period, $dimension, $instrument] = $this->idpsReferences();

        $this->postJson('/api/convivencia/idps/results', [
            'period_id' => $period->id,
            'dimension_id' => $dimension->id,
            'instrument_id' => $instrument->id,
            'academic_year_id' => null,
            'course_section_id' => null,
            'education_level_id' => null,
            'related_plan_id' => null,
            'result_scope' => 'establecimiento',
            'reference_label' => null,
            'score' => 4.25,
            'percentage' => 85,
            'sample_size' => 40,
            'qualitative_observations' => null,
            'improvement_actions' => null,
            'is_sensitive' => false,
        ])->assertCreated()
            ->assertJsonPath('data.dimension_id', $dimension->id)
            ->assertJsonPath('data.instrument_id', $instrument->id);

        $this->assertDatabaseHas('convivencia_idps_results', [
            'period_id' => $period->id,
            'dimension_id' => $dimension->id,
            'instrument_id' => $instrument->id,
        ]);
    }

    public function test_result_rejects_an_instrument_from_another_dimension(): void
    {
        $this->actAsSuperAdmin();
        [$period, $dimension] = $this->idpsReferences();
        $otherDimension = ConvivenciaIdpsDimension::query()->create([
            'code' => 'participacion',
            'name' => 'Participación y formación ciudadana',
            'active' => true,
        ]);
        $otherInstrument = ConvivenciaIdpsInstrument::query()->create([
            'dimension_id' => $otherDimension->id,
            'name' => 'Encuesta de participación',
            'response_type' => 'escala',
            'active' => true,
        ]);

        $this->postJson('/api/convivencia/idps/results', [
            'period_id' => $period->id,
            'dimension_id' => $dimension->id,
            'instrument_id' => $otherInstrument->id,
            'academic_year_id' => null,
            'course_section_id' => null,
            'education_level_id' => null,
            'related_plan_id' => null,
            'result_scope' => 'establecimiento',
            'reference_label' => null,
            'score' => 4.25,
            'percentage' => 85,
            'sample_size' => 40,
            'qualitative_observations' => null,
            'improvement_actions' => null,
            'is_sensitive' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('instrument_id')
            ->assertJsonPath(
                'errors.instrument_id.0',
                'El instrumento seleccionado está inactivo o no pertenece a la dimensión indicada.'
            );

        $this->assertDatabaseCount('convivencia_idps_results', 0);
    }

    public function test_manager_can_list_inactive_dimensions_and_instruments_to_reactivate_them(): void
    {
        $this->actAsSuperAdmin();

        $dimension = ConvivenciaIdpsDimension::query()->create([
            'code' => 'inactive_dimension',
            'name' => 'Dimensión temporalmente inactiva',
            'active' => false,
        ]);
        $instrument = ConvivenciaIdpsInstrument::query()->create([
            'dimension_id' => $dimension->id,
            'name' => 'Instrumento temporalmente inactivo',
            'response_type' => 'escala',
            'active' => false,
        ]);

        $this->getJson('/api/convivencia/idps')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $dimension->id,
                'code' => 'inactive_dimension',
                'active' => false,
            ])
            ->assertJsonFragment([
                'id' => $instrument->id,
                'name' => 'Instrumento temporalmente inactivo',
                'active' => false,
            ]);
    }

    private function actAsSuperAdmin(): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array{ConvivenciaIdpsPeriod, ConvivenciaIdpsDimension, ConvivenciaIdpsInstrument}
     */
    private function idpsReferences(): array
    {
        $period = ConvivenciaIdpsPeriod::query()->create([
            'name' => 'Diagnóstico inicial 2026',
            'status' => 'abierto',
        ]);
        $dimension = ConvivenciaIdpsDimension::query()->create([
            'code' => 'clima_convivencia',
            'name' => 'Clima de convivencia escolar',
            'active' => true,
        ]);
        $instrument = ConvivenciaIdpsInstrument::query()->create([
            'dimension_id' => $dimension->id,
            'name' => 'Encuesta de clima escolar',
            'response_type' => 'escala',
            'active' => true,
        ]);

        return [$period, $dimension, $instrument];
    }
}
