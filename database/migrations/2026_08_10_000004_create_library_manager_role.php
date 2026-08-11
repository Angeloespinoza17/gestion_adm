<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'ver_modulo_biblioteca' => 'Ver módulo Biblioteca Escolar',
        'crear_libros_biblioteca' => 'Crear libros de Biblioteca',
        'editar_libros_biblioteca' => 'Editar libros de Biblioteca',
        'eliminar_libros_biblioteca' => 'Eliminar libros de Biblioteca',
        'administrar_catalogo_biblioteca' => 'Administrar catálogo de Biblioteca',
        'administrar_inventario_biblioteca' => 'Administrar inventario de Biblioteca',
        'registrar_prestamos_biblioteca' => 'Registrar préstamos de Biblioteca',
        'registrar_devoluciones_biblioteca' => 'Registrar devoluciones de Biblioteca',
        'renovar_prestamos_biblioteca' => 'Renovar préstamos de Biblioteca',
        'gestionar_mora_biblioteca' => 'Gestionar mora de Biblioteca',
        'gestionar_reservas_biblioteca' => 'Gestionar reservas de Biblioteca',
        'gestionar_plan_lector_biblioteca' => 'Gestionar plan lector de Biblioteca',
        'gestionar_uso_espacios_biblioteca' => 'Gestionar uso de espacios de Biblioteca',
        'gestionar_categorias_biblioteca' => 'Gestionar categorías de Biblioteca',
        'gestionar_almacenaje_biblioteca' => 'Gestionar almacenaje de Biblioteca',
        'gestionar_textos_escolares_biblioteca' => 'Gestionar textos escolares',
        'gestionar_materiales_biblioteca' => 'Gestionar materiales de Biblioteca',
        'gestionar_pases_biblioteca' => 'Gestionar pases de Biblioteca',
        'ver_estadisticas_biblioteca' => 'Ver estadísticas de Biblioteca',
        'exportar_reportes_biblioteca' => 'Exportar reportes de Biblioteca',
    ];

    /** @var array<int, string> */
    private const MODULES = [
        'biblioteca',
        'biblioteca_dashboard',
        'biblioteca_catalogo',
        'biblioteca_categorias',
        'biblioteca_almacenaje',
        'biblioteca_inventario',
        'biblioteca_prestamos',
        'biblioteca_materiales',
        'biblioteca_textos_escolares',
        'biblioteca_reservas',
        'biblioteca_plan_lector',
        'biblioteca_espacios',
        'biblioteca_pases',
        'biblioteca_reportes',
    ];

    /** @var array<int, array{slug: string, name: string, route: string, sort: int}> */
    private const MODULE_DEFINITIONS = [
        ['slug' => 'biblioteca_dashboard', 'name' => 'Dashboard', 'route' => '/biblioteca', 'sort' => 1],
        ['slug' => 'biblioteca_catalogo', 'name' => 'Catálogo', 'route' => '/biblioteca/catalogo', 'sort' => 2],
        ['slug' => 'biblioteca_categorias', 'name' => 'Categorías', 'route' => '/biblioteca/categorias', 'sort' => 3],
        ['slug' => 'biblioteca_almacenaje', 'name' => 'Salas y estantes', 'route' => '/biblioteca/almacenaje', 'sort' => 4],
        ['slug' => 'biblioteca_inventario', 'name' => 'Ejemplares e inventario', 'route' => '/biblioteca/inventario', 'sort' => 5],
        ['slug' => 'biblioteca_prestamos', 'name' => 'Préstamos y devoluciones', 'route' => '/biblioteca/prestamos', 'sort' => 6],
        ['slug' => 'biblioteca_materiales', 'name' => 'Materiales', 'route' => '/biblioteca/materiales', 'sort' => 7],
        ['slug' => 'biblioteca_textos_escolares', 'name' => 'Textos escolares', 'route' => '/biblioteca/textos-escolares', 'sort' => 8],
        ['slug' => 'biblioteca_reservas', 'name' => 'Reservas de recursos', 'route' => '/biblioteca/reservas', 'sort' => 9],
        ['slug' => 'biblioteca_plan_lector', 'name' => 'Plan lector', 'route' => '/biblioteca/plan-lector', 'sort' => 10],
        ['slug' => 'biblioteca_espacios', 'name' => 'Uso de espacios', 'route' => '/biblioteca/espacios', 'sort' => 11],
        ['slug' => 'biblioteca_pases', 'name' => 'Pases de Biblioteca', 'route' => '/biblioteca/pases', 'sort' => 12],
        ['slug' => 'biblioteca_reportes', 'name' => 'Reportes', 'route' => '/biblioteca/reportes', 'sort' => 13],
    ];

    public function up(): void
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('system_modules')
        ) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            DB::table('roles')->insertOrIgnore([
                'slug' => 'encargado_biblioteca',
                'name' => 'Encargado de Biblioteca',
                'description' => 'Gestión integral del módulo Biblioteca Escolar / CRA.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('roles')
                ->where('slug', 'encargado_biblioteca')
                ->update([
                    'name' => 'Encargado de Biblioteca',
                    'description' => 'Gestión integral del módulo Biblioteca Escolar / CRA.',
                    'active' => true,
                    'updated_at' => $now,
                ]);

            foreach (self::PERMISSIONS as $slug => $name) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => 'Permiso correspondiente al módulo Biblioteca Escolar / CRA.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('permissions')
                    ->where('slug', $slug)
                    ->update([
                        'name' => $name,
                        'active' => true,
                        'updated_at' => $now,
                    ]);
            }

            $this->ensureModules($now);

            $roleId = DB::table('roles')
                ->where('slug', 'encargado_biblioteca')
                ->value('id');

            if (! $roleId) {
                return;
            }

            if (Schema::hasTable('permission_role')) {
                $permissionIds = DB::table('permissions')
                    ->whereIn('slug', array_keys(self::PERMISSIONS))
                    ->pluck('id');

                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->assignPermissionGroup($permissionIds->all(), $now);
            }

            if (Schema::hasTable('role_system_module')) {
                $moduleIds = DB::table('system_modules')
                    ->whereIn('slug', self::MODULES)
                    ->pluck('id');

                foreach ($moduleIds as $moduleId) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Cambio aditivo: el rol y sus accesos podrían estar asignados a usuarios.
    }

    private function ensureModules(mixed $now): void
    {
        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'biblioteca',
            'name' => 'Biblioteca Escolar',
            'frontend_route' => null,
            'icon' => 'bx-book-open',
            'sort_order' => 85,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('system_modules')
            ->where('slug', 'biblioteca')
            ->update([
                'name' => 'Biblioteca Escolar',
                'icon' => 'bx-book-open',
                'sort_order' => 85,
                'active' => true,
                'parent_id' => null,
                'updated_at' => $now,
            ]);

        $parentId = DB::table('system_modules')->where('slug', 'biblioteca')->value('id');

        if (! $parentId) {
            return;
        }

        foreach (self::MODULE_DEFINITIONS as $module) {
            DB::table('system_modules')->insertOrIgnore([
                'slug' => $module['slug'],
                'name' => $module['name'],
                'frontend_route' => $module['route'],
                'icon' => null,
                'sort_order' => $module['sort'],
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('system_modules')
                ->where('slug', $module['slug'])
                ->update([
                    'name' => $module['name'],
                    'frontend_route' => $module['route'],
                    'sort_order' => $module['sort'],
                    'active' => true,
                    'parent_id' => $parentId,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    private function assignPermissionGroup(array $permissionIds, mixed $now): void
    {
        if (
            ! Schema::hasTable('permission_groups')
            || ! Schema::hasTable('permission_group_permission')
        ) {
            return;
        }

        $moduleId = DB::table('system_modules')->where('slug', 'biblioteca')->value('id');

        if (! $moduleId) {
            return;
        }

        DB::table('permission_groups')->insertOrIgnore([
            'system_module_id' => $moduleId,
            'name' => 'Biblioteca',
            'slug' => 'biblioteca',
            'description' => 'Catálogo, inventario, préstamos, devoluciones, reservas, textos escolares, materiales, pases, espacios y reportes.',
            'sort_order' => 200,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permission_groups')
            ->where('slug', 'biblioteca')
            ->update([
                'system_module_id' => $moduleId,
                'name' => 'Biblioteca',
                'description' => 'Catálogo, inventario, préstamos, devoluciones, reservas, textos escolares, materiales, pases, espacios y reportes.',
                'sort_order' => 200,
                'active' => true,
                'updated_at' => $now,
            ]);

        $groupId = DB::table('permission_groups')->where('slug', 'biblioteca')->value('id');

        if (! $groupId) {
            return;
        }

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_group_permission')->insertOrIgnore([
                'permission_group_id' => $groupId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
