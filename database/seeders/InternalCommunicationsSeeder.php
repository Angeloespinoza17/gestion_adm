<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use Illuminate\Database\Seeder;

class InternalCommunicationsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            [
                'slug' => 'ver_comunicaciones_internas',
                'name' => 'Ver Comunicaciones Internas',
                'description' => 'Permite acceder al mantenedor de comunicaciones internas.',
            ],
            [
                'slug' => 'gestionar_comunicaciones_internas',
                'name' => 'Gestionar Comunicaciones Internas',
                'description' => 'Permite crear, editar, publicar y eliminar comunicaciones internas.',
            ],
        ])->map(fn (array $permission) => Permission::query()->updateOrCreate(
            ['slug' => $permission['slug']],
            [
                'name' => $permission['name'],
                'description' => $permission['description'],
                'active' => true,
            ],
        ));

        $module = SystemModule::query()->updateOrCreate(
            ['slug' => 'internal_communications'],
            [
                'name' => 'Comunicaciones internas',
                'frontend_route' => '/comunicaciones',
                'icon' => 'bx-message-square-detail',
                'sort_order' => 44,
                'active' => true,
                'parent_id' => null,
            ],
        );

        $group = PermissionGroup::query()->updateOrCreate(
            ['slug' => 'comunicaciones_internas'],
            [
                'system_module_id' => $module->id,
                'name' => 'Comunicaciones internas',
                'description' => 'Avisos internos segmentados por roles, lectura y confirmacion de recepcion.',
                'sort_order' => 25,
                'active' => true,
            ],
        );
        $group->permissions()->sync($permissions->pluck('id')->all());

        Role::query()
            ->whereIn('slug', ['super_admin', 'administrador', 'direccion', 'coordinador_academico', 'rrhh', 'inspectoria'])
            ->get()
            ->each(function (Role $role) use ($permissions, $module): void {
                $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
                $role->modules()->syncWithoutDetaching([$module->id]);
            });
    }
}
