<?php

namespace Tests\Feature\RiskPrevention;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
