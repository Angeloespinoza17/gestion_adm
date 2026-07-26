<?php

namespace Tests\Feature\CentroApuntes;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Services\CentroApuntes\CentroApuntesAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CentroApuntesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = [
        'ver_modulo_centro_apuntes',
        'crear_solicitud_impresion',
        'editar_solicitud_impresion',
        'eliminar_solicitud_impresion',
        'cambiar_estado_solicitud_impresion',
        'registrar_entrega_centro_apuntes',
        'administrar_asignaturas_centro_apuntes',
        'administrar_maquinas_centro_apuntes',
        'administrar_inventario_panol',
        'registrar_movimientos_panol',
        'solicitar_materiales_panol',
        'aprobar_entregas_panol',
        'registrar_entrega_materiales_panol',
        'ver_reportes_centro_apuntes',
        'exportar_reportes_centro_apuntes',
    ];

    public function test_permission_matrix_is_installed_and_grouped(): void
    {
        $this->assertSame(
            self::PERMISSIONS,
            Permission::query()
                ->whereIn('slug', self::PERMISSIONS)
                ->orderByRaw('CASE slug '.collect(self::PERMISSIONS)
                    ->values()
                    ->map(fn (string $slug, int $index) => "WHEN '{$slug}' THEN {$index}")
                    ->implode(' ').' END')
                ->pluck('slug')
                ->all(),
        );

        $group = PermissionGroup::query()
            ->with('permissions')
            ->where('slug', 'centro_apuntes_panol')
            ->firstOrFail();

        $this->assertTrue($group->active);
        $this->assertCount(count(self::PERMISSIONS), $group->permissions);
        $this->assertEqualsCanonicalizing(self::PERMISSIONS, $group->permissions->pluck('slug')->all());
    }

    public function test_view_permission_does_not_grant_material_request_or_delivery_actions(): void
    {
        $user = $this->userWithPermissions(['ver_modulo_centro_apuntes']);
        $access = app(CentroApuntesAccessService::class);

        $this->assertTrue($access->canViewModule($user));
        $this->assertFalse($access->canRequestMaterials($user));
        $this->assertFalse($access->canRegisterMaterialDelivery($user));
    }

    public function test_material_request_endpoint_requires_its_specific_permission(): void
    {
        $viewer = $this->userWithPermissions(['ver_modulo_centro_apuntes']);
        Sanctum::actingAs($viewer);

        $this->postJson('/api/centro-apuntes/entregas', [])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. Missing permission: solicitar_materiales_panol');

        $requester = $this->userWithPermissions([
            'ver_modulo_centro_apuntes',
            'solicitar_materiales_panol',
        ]);
        Sanctum::actingAs($requester);

        $this->postJson('/api/centro-apuntes/entregas', [])
            ->assertUnprocessable();
    }

    public function test_catalog_capabilities_expose_material_permissions_independently(): void
    {
        $user = $this->userWithPermissions([
            'ver_modulo_centro_apuntes',
            'solicitar_materiales_panol',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/centro-apuntes/catalogs')
            ->assertOk()
            ->assertJsonPath('capabilities.can_request_materials', true)
            ->assertJsonPath('capabilities.can_approve_deliveries', false)
            ->assertJsonPath('capabilities.can_register_material_delivery', false);
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol '.uniqid(),
            'slug' => 'rol_'.uniqid(),
            'active' => true,
        ]);
        $role->permissions()->attach(
            Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id')->all(),
        );

        $user = User::factory()->create([
            'user_type' => 'staff',
            'active' => true,
        ]);
        $user->roles()->attach($role);

        return $user;
    }
}
