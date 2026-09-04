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
            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'superadmin',
                'name' => 'Superadmin',
                'frontend_route' => null,
                'icon' => 'bx-lock-alt',
                'sort_order' => 118,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $suppliesParentId = DB::table('system_modules')->where('slug', 'supplies')->value('id');
            $superadminParentId = DB::table('system_modules')->where('slug', 'superadmin')->value('id');

            if ($suppliesParentId) {
                DB::table('system_modules')->insertOrIgnore([
                    'slug' => 'supplies_requests',
                    'name' => 'Solicitudes de abastecimiento',
                    'frontend_route' => '/supplies/requests',
                    'icon' => null,
                    'sort_order' => 2,
                    'active' => true,
                    'parent_id' => $suppliesParentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($superadminParentId) {
                DB::table('system_modules')->insertOrIgnore([
                    'slug' => 'superadmin_supply_requests',
                    'name' => 'Solicitudes de abastecimiento',
                    'frontend_route' => '/superadmin/solicitudes-abastecimiento',
                    'icon' => null,
                    'sort_order' => 3,
                    'active' => true,
                    'parent_id' => $superadminParentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys($permissionDefinitions))->pluck('id');
            $supplyModuleIds = DB::table('system_modules')->whereIn('slug', ['supplies', 'supplies_requests'])->pluck('id');

            if (Schema::hasTable('roles')) {
                $roles = DB::table('roles')->whereIn('slug', ['super_admin', 'administrador', 'encargado_mantencion'])->get(['id', 'slug']);
                foreach ($roles as $role) {
                    if (Schema::hasTable('permission_role')) {
                        foreach ($permissionIds as $permissionId) {
                            DB::table('permission_role')->insertOrIgnore([
                                'permission_id' => $permissionId,
                                'role_id' => $role->id,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                    if (Schema::hasTable('role_system_module')) {
                        foreach ($supplyModuleIds as $moduleId) {
                            DB::table('role_system_module')->insertOrIgnore([
                                'role_id' => $role->id,
                                'system_module_id' => $moduleId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }

                        if ($role->slug === 'super_admin') {
                            $superadminModuleIds = DB::table('system_modules')
                                ->whereIn('slug', ['superadmin', 'superadmin_supply_requests'])
                                ->pluck('id');
                            foreach ($superadminModuleIds as $moduleId) {
                                DB::table('role_system_module')->insertOrIgnore([
                                    'role_id' => $role->id,
                                    'system_module_id' => $moduleId,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]);
                            }
                        }
                    }
                }
            }

            if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
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
        });
    }

    public function down(): void
    {
        // Registro RBAC aditivo: se preservan asignaciones y trazabilidad al revertir.
    }
};
