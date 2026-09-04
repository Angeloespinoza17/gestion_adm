<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentationSeeder extends Seeder
{
    use PreventsProductionSeeding;

    private const PERMISSIONS = [
        'documentation.view' => ['Ver documentación', 'Permite consultar y descargar documentos institucionales.'],
        'documentation.create' => ['Crear documentación', 'Permite cargar nuevos documentos institucionales.'],
        'documentation.update' => ['Actualizar documentación', 'Permite editar metadatos y reemplazar archivos institucionales.'],
        'documentation.delete' => ['Eliminar documentación', 'Permite retirar documentos institucionales conservando su auditoría.'],
    ];

    public function run(): void
    {
        $this->preventProductionSeeding();

        DB::transaction(function (): void {
            $permissions = collect(self::PERMISSIONS)->mapWithKeys(function (array $definition, string $slug) {
                $permission = Permission::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $definition[0],
                        'description' => $definition[1],
                        'active' => true,
                    ],
                );

                return [$slug => $permission];
            });

            $module = SystemModule::query()->updateOrCreate(
                ['slug' => 'documentation'],
                [
                    'name' => 'Documentación',
                    'frontend_route' => '/documentation',
                    'icon' => 'bx-folder-open',
                    'sort_order' => 119,
                    'active' => true,
                    'parent_id' => null,
                ],
            );

            $group = PermissionGroup::query()->updateOrCreate(
                ['slug' => 'documentation'],
                [
                    'system_module_id' => $module->id,
                    'name' => 'Documentación',
                    'description' => 'Consulta y administración de documentos institucionales por año y versión.',
                    'sort_order' => 119,
                    'active' => true,
                ],
            );
            $group->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());

            Role::query()
                ->whereIn('slug', ['super_admin', 'administrador'])
                ->get()
                ->each(function (Role $role) use ($module, $permissions): void {
                    $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
                    $role->modules()->syncWithoutDetaching([$module->id]);
                });

            Role::query()
                ->where('slug', 'direccion')
                ->get()
                ->each(function (Role $role) use ($module, $permissions): void {
                    $role->permissions()->syncWithoutDetaching(
                        $permissions->only([
                            'documentation.view',
                            'documentation.create',
                            'documentation.update',
                        ])->pluck('id')->all()
                    );
                    $role->modules()->syncWithoutDetaching([$module->id]);
                });
        }, 3);
    }
}
