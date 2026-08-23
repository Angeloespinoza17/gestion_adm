<?php

namespace Tests\Feature\RiskPrevention;

use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\RiskPrevention\RiskMatrixConfigurationInstaller;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiskPreventionRoleEppAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_prevention_role_keeps_epp_delivery_module_and_management_permissions(): void
    {
        $this->seed(RbacSeeder::class);

        $role = Role::query()
            ->where('slug', 'prevencion_riesgos')
            ->with(['permissions:id,slug', 'modules:id,slug'])
            ->firstOrFail();

        $this->assertContains('ver_prevencion_riesgos', $role->permissions->pluck('slug')->all());
        $this->assertContains('gestionar_prevencion_riesgos', $role->permissions->pluck('slug')->all());
        $this->assertContains('exportar_prevencion_riesgos', $role->permissions->pluck('slug')->all());
        $this->assertContains('risk_prevention', $role->modules->pluck('slug')->all());
        $this->assertContains('risk_prevention_epp', $role->modules->pluck('slug')->all());

        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $this->getJson('/api/risk-prevention/epp/delivery-records')->assertOk();
        $this->postJson('/api/risk-prevention/epp/delivery-records', [])->assertUnprocessable();
    }

    public function test_prevention_and_super_admin_roles_receive_complete_risk_prevention_navigation_and_permissions(): void
    {
        $this->seed(RbacSeeder::class);

        $expectedPermissions = array_merge([
            'ver_prevencion_riesgos',
            'gestionar_prevencion_riesgos',
            'exportar_prevencion_riesgos',
            'ver_documentos_prevencion_difundibles',
        ], array_keys(app(RiskMatrixConfigurationInstaller::class)->permissions()));
        $expectedModules = [
            'risk_prevention',
            'risk_prevention_dashboard',
            'risk_prevention_risk_matrices',
            'risk_prevention_risk_imports',
            'risk_prevention_risk_catalogs',
            'risk_prevention_preventive_program',
            'risk_prevention_extinguishers',
            'risk_prevention_accidents',
            'risk_prevention_emergencies',
            'risk_prevention_epp',
            'risk_prevention_trainings',
            'risk_prevention_personnel',
            'risk_prevention_documents',
            'risk_prevention_staff_documents',
            'risk_prevention_reports',
        ];

        foreach (['super_admin', 'prevencion_riesgos'] as $roleSlug) {
            $role = Role::query()
                ->where('slug', $roleSlug)
                ->with(['permissions:id,slug', 'modules:id,slug'])
                ->firstOrFail();

            $this->assertEmpty(array_diff($expectedPermissions, $role->permissions->pluck('slug')->all()));
            $this->assertEmpty(array_diff($expectedModules, $role->modules->pluck('slug')->all()));
        }

        $parentId = SystemModule::query()->where('slug', 'risk_prevention')->value('id');
        $this->assertSame(
            array_slice($expectedModules, 1),
            SystemModule::query()
                ->where('parent_id', $parentId)
                ->orderBy('sort_order')
                ->pluck('slug')
                ->all(),
        );

        $role = Role::query()->where('slug', 'prevencion_riesgos')->firstOrFail();
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $moduleSlugs = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug')->all();
        $this->assertEmpty(array_diff($expectedModules, $moduleSlugs));
    }

    public function test_legacy_dashboard_is_not_blocked_by_the_independent_risk_matrix_installation_check(): void
    {
        $service = app(RiskPreventionAccessService::class);

        $this->assertNotContains('prevent_risk_matrices', $service->requiredTables());
        $this->assertContains('prevent_risk_matrices', $service->riskMatrixRequiredTables());

        $dashboardMiddleware = app('router')->getRoutes()
            ->match(Request::create('/api/risk-prevention/dashboard', 'GET'))
            ->gatherMiddleware();
        $matrixMiddleware = app('router')->getRoutes()
            ->match(Request::create('/api/risk-prevention/risk-matrices', 'GET'))
            ->gatherMiddleware();

        $this->assertContains('risk_prevention.installed', $dashboardMiddleware);
        $this->assertNotContains('risk_matrix.installed', $dashboardMiddleware);
        $this->assertContains('risk_prevention.installed', $matrixMiddleware);
        $this->assertContains('risk_matrix.installed', $matrixMiddleware);
    }
}
