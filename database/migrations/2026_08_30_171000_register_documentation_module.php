<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'documentation.view' => ['Ver documentación', 'Permite consultar y descargar documentos institucionales.'],
        'documentation.create' => ['Crear documentación', 'Permite cargar nuevos documentos institucionales.'],
        'documentation.update' => ['Actualizar documentación', 'Permite editar metadatos y reemplazar archivos institucionales.'],
        'documentation.delete' => ['Eliminar documentación', 'Permite retirar documentos institucionales conservando su auditoría.'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            foreach (self::PERMISSIONS as $slug => [$name, $description]) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => $description,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'documentation',
                'name' => 'Documentación',
                'frontend_route' => '/documentation',
                'icon' => 'bx-folder-open',
                'sort_order' => 119,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $moduleId = DB::table('system_modules')->where('slug', 'documentation')->value('id');
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_keys(self::PERMISSIONS))
                ->pluck('id', 'slug');

            if ($moduleId && Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                DB::table('permission_groups')->insertOrIgnore([
                    'slug' => 'documentation',
                    'system_module_id' => $moduleId,
                    'name' => 'Documentación',
                    'description' => 'Consulta y administración de documentos institucionales por año y versión.',
                    'sort_order' => 119,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $groupId = DB::table('permission_groups')->where('slug', 'documentation')->value('id');
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_group_permission')->insertOrIgnore([
                        'permission_group_id' => $groupId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (! Schema::hasTable('roles')) {
                return;
            }

            $roles = DB::table('roles')
                ->whereIn('slug', ['super_admin', 'administrador', 'direccion'])
                ->get(['id', 'slug']);

            foreach ($roles as $role) {
                if (Schema::hasTable('permission_role')) {
                    $slugs = $role->slug === 'direccion'
                        ? ['documentation.view', 'documentation.create', 'documentation.update']
                        : array_keys(self::PERMISSIONS);

                    foreach ($slugs as $slug) {
                        if ($permissionId = $permissionIds[$slug] ?? null) {
                            DB::table('permission_role')->insertOrIgnore([
                                'role_id' => $role->id,
                                'permission_id' => $permissionId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }

                if ($moduleId && Schema::hasTable('role_system_module')) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $role->id,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }, 3);
    }

    public function down(): void
    {
        // Catálogo RBAC aditivo y conservador: insertOrIgnore permite que alguno
        // de estos slugs existiera antes de esta migración. Sin una marca de
        // propiedad fiable, un rollback no puede distinguir registros propios
        // de permisos, módulos, grupos o asignaciones preexistentes. Se
        // conservan deliberadamente para no revocar accesos ni borrar datos
        // administrados posteriormente. La tabla funcional tiene su rollback
        // reversible en la migración create_managed_documents_table.
    }
};
