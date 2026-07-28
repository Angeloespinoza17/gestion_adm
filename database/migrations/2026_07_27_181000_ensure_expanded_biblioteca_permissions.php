<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();
        $permissions = [
            'gestionar_categorias_biblioteca' => 'Gestionar categorías de Biblioteca',
            'gestionar_almacenaje_biblioteca' => 'Gestionar almacenaje de Biblioteca',
            'gestionar_textos_escolares_biblioteca' => 'Gestionar textos escolares',
            'gestionar_materiales_biblioteca' => 'Gestionar materiales de Biblioteca',
            'gestionar_pases_biblioteca' => 'Gestionar pases de Biblioteca',
        ];

        foreach ($permissions as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $name.'.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $parentId = DB::table('system_modules')->where('slug', 'biblioteca')->value('id');

        if ($parentId) {
            $modules = [
                ['slug' => 'biblioteca_categorias', 'name' => 'Categorías', 'route' => '/biblioteca/categorias', 'sort' => 3],
                ['slug' => 'biblioteca_almacenaje', 'name' => 'Almacenaje', 'route' => '/biblioteca/almacenaje', 'sort' => 4],
                ['slug' => 'biblioteca_materiales', 'name' => 'Materiales', 'route' => '/biblioteca/materiales', 'sort' => 8],
                ['slug' => 'biblioteca_textos_escolares', 'name' => 'Textos escolares', 'route' => '/biblioteca/textos-escolares', 'sort' => 9],
                ['slug' => 'biblioteca_pases', 'name' => 'Pases de biblioteca', 'route' => '/biblioteca/pases', 'sort' => 10],
            ];

            foreach ($modules as $module) {
                DB::table('system_modules')->updateOrInsert(
                    ['slug' => $module['slug']],
                    [
                        'name' => $module['name'],
                        'frontend_route' => $module['route'],
                        'icon' => null,
                        'sort_order' => $module['sort'],
                        'active' => true,
                        'parent_id' => $parentId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        $this->grantToRoles(array_keys($permissions), [
            'biblioteca_categorias',
            'biblioteca_almacenaje',
            'biblioteca_materiales',
            'biblioteca_textos_escolares',
            'biblioteca_pases',
        ], ['super_admin', 'administrador']);

        $this->grantToRoles(
            ['gestionar_pases_biblioteca'],
            ['biblioteca_pases'],
            ['inspectoria']
        );
    }

    public function down(): void
    {
        $permissionSlugs = [
            'gestionar_categorias_biblioteca',
            'gestionar_almacenaje_biblioteca',
            'gestionar_textos_escolares_biblioteca',
            'gestionar_materiales_biblioteca',
            'gestionar_pases_biblioteca',
        ];
        $moduleSlugs = [
            'biblioteca_categorias',
            'biblioteca_almacenaje',
            'biblioteca_materiales',
            'biblioteca_textos_escolares',
            'biblioteca_pases',
        ];

        if (Schema::hasTable('role_system_module')) {
            $moduleIds = DB::table('system_modules')->whereIn('slug', $moduleSlugs)->pluck('id');
            DB::table('role_system_module')->whereIn('system_module_id', $moduleIds)->delete();
        }

        if (Schema::hasTable('permission_role')) {
            $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('system_modules')->whereIn('slug', $moduleSlugs)->delete();
        DB::table('permissions')->whereIn('slug', $permissionSlugs)->delete();
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     * @param  array<int, string>  $moduleSlugs
     * @param  array<int, string>  $roleSlugs
     */
    private function grantToRoles(array $permissionSlugs, array $moduleSlugs, array $roleSlugs): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleIds = DB::table('roles')->whereIn('slug', $roleSlugs)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');
        $moduleIds = DB::table('system_modules')->whereIn('slug', $moduleSlugs)->pluck('id');
        $now = now();

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($moduleIds as $moduleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $roleId,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
