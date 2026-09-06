<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaIdpsDimension;
use App\Models\Convivencia\ConvivenciaIdpsInstrument;
use App\Models\Convivencia\ConvivenciaIdpsPeriod;
use App\Models\Convivencia\ConvivenciaIdpsResult;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ConvivenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_declares_its_limit_and_export_pages_return_every_authorized_record(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $user = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        Sanctum::actingAs($user);

        $baseCase = ConvivenciaCase::query()->firstOrFail();
        foreach (range(1, 63) as $index) {
            $case = $baseCase->replicate();
            $case->folio = sprintf('CE-EXPORT-%03d', $index);
            $case->opened_at = now()->subMinutes($index);
            $case->is_sensitive = false;
            $case->save();
        }

        $expectedTotal = ConvivenciaCase::query()->count();
        $preview = $this->getJson('/api/convivencia/reports/course')
            ->assertOk()
            ->assertJsonCount(50, 'lists.cases')
            ->assertJsonPath('list_meta.cases.shown', 50)
            ->assertJsonPath('list_meta.cases.total', $expectedTotal)
            ->assertJsonPath('list_meta.cases.truncated', true)
            ->assertJsonPath('list_meta.cases.limit', 50)
            ->assertJsonStructure([
                'summary' => [
                    'total_cases', 'open_cases', 'closed_cases', 'case_resolution_rate',
                    'complaints', 'complaint_conversion_rate', 'measure_completion_rate',
                ],
                'analytics' => [
                    'activity_by_type', 'cases_by_status', 'cases_by_classification',
                    'cases_by_subclassification', 'cases_by_criticality', 'cases_by_origin',
                    'complaints_by_status', 'complaints_by_type', 'complaints_by_complainant',
                    'derivations_by_scope', 'derivations_by_status', 'derivations_by_priority',
                    'measures_by_status', 'measures_by_type', 'interviews_by_follow_up',
                    'interviews_by_type', 'daily_logs_by_type', 'daily_logs_by_status',
                    'monthly_activity' => ['labels', 'series'], 'courses',
                ],
                'lists' => ['complaints'],
                'list_meta' => ['complaints' => ['shown', 'total', 'truncated', 'limit']],
            ]);

        $this->assertArrayNotHasKey('initial_report', $preview->json('lists.cases.0'));

        $exportedIds = [];
        $page = 1;
        do {
            $response = $this->getJson('/api/convivencia/reports/course/export-data?dataset=cases&per_page=25&page='.$page)
                ->assertOk()
                ->assertJsonPath('dataset', 'cases')
                ->assertJsonPath('current_page', $page)
                ->assertJsonPath('total', $expectedTotal);

            foreach ($response->json('data') as $row) {
                $this->assertSame(
                    ['id', 'folio', 'opened_at', 'classification_label', 'criticality_label', 'status'],
                    array_keys($row),
                );
                $exportedIds[] = $row['id'];
            }
            $lastPage = $response->json('last_page');
            $page++;
        } while ($page <= $lastPage);

        $this->assertCount($expectedTotal, $exportedIds);
        $this->assertCount($expectedTotal, array_unique($exportedIds));

        $complaints = $this->getJson('/api/convivencia/reports/course/export-data?dataset=complaints&per_page=25&page=1')
            ->assertOk()
            ->assertJsonPath('dataset', 'complaints');
        if ($complaints->json('data')) {
            $this->assertSame(
                ['id', 'folio', 'received_at', 'situation_type_label', 'complainant_type', 'status'],
                array_keys($complaints->json('data.0')),
            );
        }
    }

    public function test_complete_export_requires_both_view_and_export_permissions(): void
    {
        $this->seed(ConvivenciaSeeder::class);

        $onlyExport = $this->userWithPermissions(['exportar_reportes_convivencia'], 'solo_exporta');
        Sanctum::actingAs($onlyExport);
        $this->getJson('/api/convivencia/reports/course/export-data?dataset=cases')->assertForbidden();

        $onlyView = $this->userWithPermissions(['ver_reportes_curso_convivencia'], 'solo_visualiza');
        Sanctum::actingAs($onlyView);
        $this->getJson('/api/convivencia/reports/course/export-data?dataset=cases')->assertForbidden();

        $viewAndExport = $this->userWithPermissions([
            'ver_reportes_curso_convivencia',
            'exportar_reportes_convivencia',
        ], 'visualiza_y_exporta');
        Sanctum::actingAs($viewAndExport);
        $this->getJson('/api/convivencia/reports/course/export-data?dataset=cases')
            ->assertOk()
            ->assertJsonStructure(['dataset', 'data', 'current_page', 'last_page', 'per_page', 'total']);
    }

    public function test_sensitive_idps_observations_are_hidden_from_report_viewers_and_visible_with_sensitive_access(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $owner = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        $period = ConvivenciaIdpsPeriod::query()->firstOrCreate(
            ['name' => 'Período privado de prueba'],
            ['status' => 'abierto', 'created_by' => $owner->id, 'updated_by' => $owner->id],
        );
        $dimension = ConvivenciaIdpsDimension::query()->firstOrCreate(
            ['code' => 'clima_convivencia'],
            ['name' => 'Clima de convivencia', 'active' => true, 'created_by' => $owner->id, 'updated_by' => $owner->id],
        );
        $instrument = ConvivenciaIdpsInstrument::query()->create([
            'dimension_id' => $dimension->id,
            'name' => 'Instrumento privado de prueba',
            'response_type' => 'escala',
            'active' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $marker = 'MARCADOR_IDPS_SENSIBLE_NO_DIVULGAR_2099';
        $result = ConvivenciaIdpsResult::query()->create([
            'period_id' => $period->id,
            'dimension_id' => $dimension->id,
            'instrument_id' => $instrument->id,
            'result_scope' => 'establecimiento',
            'reference_label' => 'Resultado reservado',
            'percentage' => 67,
            'qualitative_observations' => $marker,
            'is_sensitive' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $viewer = $this->userWithPermissions(['ver_reportes_curso_convivencia'], 'reporte_sin_sensibles');
        Sanctum::actingAs($viewer);
        $idpsResponse = $this->getJson('/api/convivencia/idps')->assertOk()->assertDontSee($marker, false);
        $this->assertFalse(collect($idpsResponse->json('results.data'))->contains('id', $result->id));
        $this->getJson('/api/convivencia/reports/course')->assertOk()->assertDontSee($marker, false);

        $authorized = $this->userWithPermissions([
            'ver_reportes_curso_convivencia',
            'ver_casos_sensibles_convivencia',
        ], 'reporte_con_sensibles');
        Sanctum::actingAs($authorized);
        $this->getJson('/api/convivencia/idps')
            ->assertOk()
            ->assertJsonFragment(['id' => $result->id, 'qualitative_observations' => $marker]);
        $this->getJson('/api/convivencia/reports/course')
            ->assertOk()
            ->assertJsonPath('summary.climate.observations', $marker);
    }

    private function userWithPermissions(array $slugs, string $roleSlug): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => str_replace('_', ' ', ucfirst($roleSlug)),
            'slug' => $roleSlug,
            'active' => true,
        ]);
        $permissionIds = Permission::query()->whereIn('slug', $slugs)->pluck('id');
        $this->assertCount(count($slugs), $permissionIds);
        $role->permissions()->attach($permissionIds);
        $user->roles()->attach($role);

        return $user->fresh();
    }
}
