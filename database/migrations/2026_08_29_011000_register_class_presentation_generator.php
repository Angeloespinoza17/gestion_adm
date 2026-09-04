<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'class-presentations.view' => ['Ver generador de clases', 'Permite consultar las presentaciones propias.'],
        'class-presentations.view-all' => ['Ver todas las presentaciones', 'Permite consultar presentaciones autorizadas del establecimiento.'],
        'class-presentations.create' => ['Generar clases', 'Permite solicitar nuevas presentaciones mediante OpenAI.'],
        'class-presentations.download' => ['Descargar presentaciones', 'Permite descargar archivos PPTX, PDF y previsualizaciones autorizados.'],
        'class-presentations.regenerate' => ['Regenerar clases', 'Permite crear nuevas versiones sin eliminar las anteriores.'],
        'class-presentations.archive' => ['Archivar presentaciones', 'Permite archivar presentaciones sin eliminar archivos.'],
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
                'slug' => 'pedagogical_management',
                'name' => 'Gestión pedagógica',
                'frontend_route' => null,
                'icon' => 'bx-book-content',
                'sort_order' => 22,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $parentId = DB::table('system_modules')->where('slug', 'pedagogical_management')->value('id');
            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'class_presentation_generator',
                'name' => 'Generador de clases',
                'frontend_route' => '/gestion-pedagogica/generador-clases',
                'icon' => 'bx-slideshow',
                'sort_order' => 2,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id', 'slug');
            $moduleIds = DB::table('system_modules')->whereIn('slug', ['pedagogical_management', 'class_presentation_generator'])->pluck('id');
            $fullRoles = ['super_admin', 'administrador', 'direccion', 'coordinador_academico', 'jefe_utp'];
            $teacherPermissions = ['class-presentations.view', 'class-presentations.create', 'class-presentations.download', 'class-presentations.regenerate'];
            $roles = Schema::hasTable('roles') ? DB::table('roles')->whereIn('slug', [...$fullRoles, 'docente'])->get(['id', 'slug']) : collect();

            if (Schema::hasTable('permission_role')) {
                foreach ($roles as $role) {
                    $slugs = in_array($role->slug, $fullRoles, true) ? array_keys(self::PERMISSIONS) : $teacherPermissions;
                    foreach ($slugs as $slug) {
                        if ($permissionId = $permissionIds[$slug] ?? null) {
                            DB::table('permission_role')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
                        }
                    }
                }
            }

            if (Schema::hasTable('role_system_module')) {
                foreach ($roles as $role) {
                    foreach ($moduleIds as $moduleId) {
                        DB::table('role_system_module')->insertOrIgnore(['role_id' => $role->id, 'system_module_id' => $moduleId, 'created_at' => $now, 'updated_at' => $now]);
                    }
                }
            }

            if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                DB::table('permission_groups')->insertOrIgnore([
                    'slug' => 'gestion_pedagogica',
                    'system_module_id' => $parentId,
                    'name' => 'Gestión pedagógica',
                    'description' => 'Permisos de instrumentos y generación de clases.',
                    'sort_order' => 1,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $groupId = DB::table('permission_groups')->where('slug', 'gestion_pedagogica')->value('id');
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_group_permission')->insertOrIgnore(['permission_group_id' => $groupId, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }, 3);
    }

    public function down(): void
    {
        // Forward-only: conserva permisos, módulos y asignaciones ya otorgadas.
    }
};
