<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SensitiveFinancialModulesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_permissions_and_modules_do_not_expose_sensitive_modules_without_confidential_gate(): void
    {
        $user = $this->userWithAccess(
            [
                'contabilidad.ver',
                'contabilidad.dashboard',
                'remuneraciones.ver',
                'remuneraciones.dashboard',
            ],
            [
                'accounting',
                'accounting_dashboard',
                'remuneration',
                'remuneration_dashboard',
            ],
        );

        Sanctum::actingAs($user);

        $userCount = User::query()->count();
        $permissionCount = Permission::query()->count();

        $modules = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug');

        $this->assertFalse($modules->contains('accounting'));
        $this->assertFalse($modules->contains('accounting_dashboard'));
        $this->assertFalse($modules->contains('remuneration'));
        $this->assertFalse($modules->contains('remuneration_dashboard'));

        $this->getJson('/api/contabilidad/catalogs')->assertForbidden();
        $this->getJson('/api/remuneraciones/catalogs')->assertForbidden();

        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($permissionCount, Permission::query()->count());
    }

    public function test_confidential_gate_without_specific_permission_only_exposes_parent_and_no_sensitive_catalog_data(): void
    {
        $user = $this->userWithAccess(
            [
                'contabilidad.acceso_confidencial',
                'contabilidad.ver',
                'remuneraciones.acceso_confidencial',
                'remuneraciones.ver',
            ],
            [
                'accounting',
                'accounting_dashboard',
                'remuneration',
                'remuneration_dashboard',
            ],
        );

        Sanctum::actingAs($user);

        $modules = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug');

        $this->assertTrue($modules->contains('accounting'));
        $this->assertFalse($modules->contains('accounting_dashboard'));
        $this->assertTrue($modules->contains('remuneration'));
        $this->assertFalse($modules->contains('remuneration_dashboard'));

        $accountingCatalogs = $this->getJson('/api/contabilidad/catalogs')
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('data', []);

        $remunerationCatalogs = $this->getJson('/api/remuneraciones/catalogs')
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('data', []);

        $accountingCatalogs->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $remunerationCatalogs->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->getJson('/api/contabilidad/dashboard')->assertForbidden();
        $this->getJson('/api/remuneraciones/dashboard')->assertForbidden();
        $this->getJson('/api/contabilidad/resources/incomes')->assertForbidden();
        $this->getJson('/api/remuneraciones/resources/payrolls')->assertForbidden();
    }

    public function test_authorized_user_only_receives_explicitly_allowed_sensitive_navigation(): void
    {
        $user = $this->userWithAccess(
            [
                'contabilidad.acceso_confidencial',
                'contabilidad.ver',
                'contabilidad.dashboard',
                'remuneraciones.acceso_confidencial',
                'remuneraciones.ver',
                'remuneraciones.dashboard',
            ],
            [
                'accounting',
                'accounting_dashboard',
                'accounting_incomes',
                'remuneration',
                'remuneration_dashboard',
                'remuneration_payrolls',
            ],
        );

        Sanctum::actingAs($user);

        $modules = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug');

        $this->assertTrue($modules->contains('accounting'));
        $this->assertTrue($modules->contains('accounting_dashboard'));
        $this->assertFalse($modules->contains('accounting_incomes'));
        $this->assertTrue($modules->contains('remuneration'));
        $this->assertTrue($modules->contains('remuneration_dashboard'));
        $this->assertFalse($modules->contains('remuneration_payrolls'));

        $this->getJson('/api/contabilidad/dashboard')->assertOk();
        $this->getJson('/api/remuneraciones/dashboard')->assertOk();
        $this->getJson('/api/contabilidad/resources/incomes')->assertForbidden();
        $this->getJson('/api/remuneraciones/resources/payrolls')->assertForbidden();
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     * @param  array<int, string>  $moduleSlugs
     */
    private function userWithAccess(array $permissionSlugs, array $moduleSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Perfil prueba confidencial ' . uniqid(),
            'slug' => 'perfil_prueba_' . uniqid(),
            'active' => true,
        ]);

        $role->permissions()->attach(
            Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id')->all(),
        );
        $role->modules()->attach(
            SystemModule::query()->whereIn('slug', $moduleSlugs)->pluck('id')->all(),
        );

        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach($role->id);

        return $user;
    }
}
