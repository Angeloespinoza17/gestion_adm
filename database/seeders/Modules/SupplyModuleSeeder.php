<?php

namespace Database\Seeders\Modules;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use Illuminate\Database\Seeder;

class SupplyModuleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['slug' => 'ver_abastecimiento', 'name' => 'Ver Abastecimiento'],
            ['slug' => 'gestionar_insumos_abastecimiento', 'name' => 'Gestionar catálogo de Abastecimiento'],
            ['slug' => 'registrar_compras_abastecimiento', 'name' => 'Registrar compras de Abastecimiento'],
            ['slug' => 'registrar_entregas_abastecimiento', 'name' => 'Registrar entregas de Abastecimiento'],
            ['slug' => 'exportar_actas_abastecimiento', 'name' => 'Exportar actas de entrega de Abastecimiento'],
            ['slug' => 'ver_solicitudes_abastecimiento', 'name' => 'Ver solicitudes de Abastecimiento'],
            ['slug' => 'crear_solicitudes_abastecimiento', 'name' => 'Crear solicitudes de Abastecimiento'],
        ])->map(function (array $definition): Permission {
            return Permission::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => 'Permiso operativo del módulo de Abastecimiento.',
                    'active' => true,
                ],
            );
        });

        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'supplies'],
            [
                'name' => 'Abastecimiento',
                'frontend_route' => null,
                'icon' => 'bx-package',
                'sort_order' => 95,
                'active' => true,
                'parent_id' => null,
            ],
        );

        $children = collect([
            ['slug' => 'supplies_cleaning', 'name' => 'Insumos de aseo', 'route' => '/supplies/cleaning', 'sort' => 1],
            ['slug' => 'supplies_requests', 'name' => 'Solicitudes de abastecimiento', 'route' => '/supplies/requests', 'sort' => 2],
            ['slug' => 'supplies_heating', 'name' => 'Combustibles y calefacción', 'route' => '/supplies/heating', 'sort' => 3],
            ['slug' => 'supplies_maintenance_storeroom', 'name' => 'Pañol de mantenimiento', 'route' => '/supplies/maintenance-storeroom', 'sort' => 4],
        ])->map(function (array $definition) use ($parent): SystemModule {
            return SystemModule::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'frontend_route' => $definition['route'],
                    'icon' => null,
                    'sort_order' => $definition['sort'],
                    'active' => true,
                    'parent_id' => $parent->id,
                ],
            );
        });

        $group = PermissionGroup::query()->updateOrCreate(
            ['slug' => 'abastecimiento'],
            [
                'system_module_id' => $parent->id,
                'name' => 'Abastecimiento',
                'description' => 'Catálogo, compras, stock, entregas y actas de insumos institucionales.',
                'sort_order' => 145,
                'active' => true,
            ],
        );
        $group->permissions()->sync($permissions->pluck('id')->all());

        $moduleIds = $children->prepend($parent)->pluck('id')->all();

        $superadminParent = SystemModule::query()->updateOrCreate(
            ['slug' => 'superadmin'],
            [
                'name' => 'Superadmin',
                'frontend_route' => null,
                'icon' => 'bx-lock-alt',
                'sort_order' => 118,
                'active' => true,
                'parent_id' => null,
            ],
        );
        $superadminRequests = SystemModule::query()->updateOrCreate(
            ['slug' => 'superadmin_supply_requests'],
            [
                'name' => 'Solicitudes de abastecimiento',
                'frontend_route' => '/superadmin/solicitudes-abastecimiento',
                'icon' => null,
                'sort_order' => 3,
                'active' => true,
                'parent_id' => $superadminParent->id,
            ],
        );

        Role::query()
            ->whereIn('slug', ['super_admin', 'administrador', 'encargado_mantencion'])
            ->get()
            ->each(function (Role $role) use ($permissions, $moduleIds): void {
                $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
                $role->modules()->syncWithoutDetaching($moduleIds);
            });

        Role::query()
            ->where('slug', 'super_admin')
            ->first()?->modules()
            ->syncWithoutDetaching([$superadminParent->id, $superadminRequests->id]);
    }
}
