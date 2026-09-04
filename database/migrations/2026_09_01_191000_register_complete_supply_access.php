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

        DB::transaction(function (): void {
            $now = now();
            $permissionDefinitions = [
                'ver_abastecimiento' => 'Ver Abastecimiento',
                'gestionar_insumos_abastecimiento' => 'Gestionar catálogo de Abastecimiento',
                'registrar_compras_abastecimiento' => 'Registrar compras de Abastecimiento',
                'registrar_entregas_abastecimiento' => 'Registrar entregas de Abastecimiento',
                'exportar_actas_abastecimiento' => 'Exportar actas de entrega de Abastecimiento',
                'ver_solicitudes_abastecimiento' => 'Ver solicitudes de Abastecimiento',
                'crear_solicitudes_abastecimiento' => 'Crear solicitudes de Abastecimiento',
            ];

            foreach ($permissionDefinitions as $slug => $name) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => 'Permiso operativo del módulo de Abastecimiento.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'supplies',
                'name' => 'Abastecimiento',
                'frontend_route' => null,
                'icon' => 'bx-package',
                'sort_order' => 95,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $parentId = DB::table('system_modules')->where('slug', 'supplies')->value('id');
            if (! $parentId) {
                return;
            }

            $moduleDefinitions = [
                ['slug' => 'supplies_cleaning', 'name' => 'Insumos de aseo', 'route' => '/supplies/cleaning', 'sort' => 1],
                ['slug' => 'supplies_requests', 'name' => 'Solicitudes de abastecimiento', 'route' => '/supplies/requests', 'sort' => 2],
                ['slug' => 'supplies_heating', 'name' => 'Combustibles y calefacción', 'route' => '/supplies/heating', 'sort' => 3],
                ['slug' => 'supplies_maintenance_storeroom', 'name' => 'Pañol de mantenimiento', 'route' => '/supplies/maintenance-storeroom', 'sort' => 4],
            ];

            foreach ($moduleDefinitions as $definition) {
                DB::table('system_modules')->insertOrIgnore([
                    'slug' => $definition['slug'],
                    'name' => $definition['name'],
                    'frontend_route' => $definition['route'],
                    'icon' => null,
                    'sort_order' => $definition['sort'],
                    'active' => true,
                    'parent_id' => $parentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_keys($permissionDefinitions))
                ->pluck('id');
            $moduleIds = DB::table('system_modules')
                ->whereIn('slug', array_merge(['supplies'], array_column($moduleDefinitions, 'slug')))
                ->pluck('id');

            if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                DB::table('permission_groups')->insertOrIgnore([
                    'slug' => 'abastecimiento',
                    'system_module_id' => $parentId,
                    'name' => 'Abastecimiento',
                    'description' => 'Catálogo, compras, stock, entregas y actas de insumos institucionales.',
                    'sort_order' => 145,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $groupId = DB::table('permission_groups')->where('slug', 'abastecimiento')->value('id');
                if ($groupId) {
                    foreach ($permissionIds as $permissionId) {
                        DB::table('permission_group_permission')->insertOrIgnore([
                            'permission_group_id' => $groupId,
                            'permission_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            if (! Schema::hasTable('roles')) {
                return;
            }

            $roleIds = DB::table('roles')
                ->whereIn('slug', ['super_admin', 'administrador', 'encargado_mantencion'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                if (Schema::hasTable('permission_role')) {
                    foreach ($permissionIds as $permissionId) {
                        DB::table('permission_role')->insertOrIgnore([
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                if (Schema::hasTable('role_system_module')) {
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
        });
    }

    public function down(): void
    {
        // Registro RBAC aditivo: se preservan permisos, módulos y asignaciones.
    }
};
