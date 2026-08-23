<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VIEW_PERMISSION = 'ver_licencias_medicas_estudiantes';

    private const CREATE_PERMISSION = 'crear_licencias_medicas_estudiantes';

    public function up(): void
    {
        $this->expandMedicalCertificates();
        $this->registerAccess();
    }

    public function down(): void
    {
        // Migración aditiva: no se eliminan columnas, registros, accesos ni navegación.
    }

    private function expandMedicalCertificates(): void
    {
        if (! Schema::hasTable('student_medical_certificates')) {
            return;
        }

        if (! Schema::hasColumn('student_medical_certificates', 'is_permanent')) {
            Schema::table('student_medical_certificates', function (Blueprint $table): void {
                $table->boolean('is_permanent')->default(false)->index();
            });
        }

        if (! Schema::hasColumn('student_medical_certificates', 'source_module')) {
            Schema::table('student_medical_certificates', function (Blueprint $table): void {
                $table->string('source_module', 30)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('student_medical_certificates', 'registered_by')) {
            Schema::table('student_medical_certificates', function (Blueprint $table): void {
                $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        // Los registros administrativos pueden existir sin un archivo adjunto. Hacer estos
        // metadatos opcionales conserva íntegramente los documentos privados ya almacenados.
        Schema::table('student_medical_certificates', function (Blueprint $table): void {
            $table->string('private_path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
            $table->string('mime_type', 120)->nullable()->change();
            $table->unsignedBigInteger('size_bytes')->nullable()->change();
            $table->string('sha256', 64)->nullable()->change();
        });
    }

    private function registerAccess(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $permissions = [
                self::VIEW_PERMISSION => [
                    'name' => 'Ver licencias médicas de estudiantes',
                    'description' => 'Permite consultar el resumen administrativo compartido de licencias y certificados médicos.',
                ],
                self::CREATE_PERMISSION => [
                    'name' => 'Registrar licencias médicas de estudiantes',
                    'description' => 'Permite registrar licencias y condiciones crónicas en el repositorio compartido.',
                ],
            ];

            foreach ($permissions as $slug => $definition) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('permissions')->where('slug', $slug)->update([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'active' => true,
                    'updated_at' => $now,
                ]);
            }

            $modules = [
                [
                    'slug' => 'infirmary_medical_leaves',
                    'name' => 'Licencias médicas',
                    'frontend_route' => '/infirmary/medical-leaves',
                    'icon' => 'bx-file-blank',
                    'sort_order' => 8,
                    'parent_slug' => 'infirmary',
                ],
                [
                    'slug' => 'inspectoria_medical_leaves',
                    'name' => 'Licencias médicas',
                    'frontend_route' => '/inspectoria/licencias-medicas',
                    'icon' => 'bx-file-blank',
                    'sort_order' => 9,
                    'parent_slug' => 'inspectoria',
                ],
            ];

            foreach ($modules as $module) {
                $parentId = DB::table('system_modules')->where('slug', $module['parent_slug'])->value('id');
                DB::table('system_modules')->insertOrIgnore([
                    'slug' => $module['slug'],
                    'name' => $module['name'],
                    'frontend_route' => $module['frontend_route'],
                    'icon' => $module['icon'],
                    'sort_order' => $module['sort_order'],
                    'parent_id' => $parentId,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('system_modules')->where('slug', $module['slug'])->update([
                    'name' => $module['name'],
                    'frontend_route' => $module['frontend_route'],
                    'icon' => $module['icon'],
                    'sort_order' => $module['sort_order'],
                    'parent_id' => $parentId,
                    'active' => true,
                    'updated_at' => $now,
                ]);
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_keys($permissions))
                ->pluck('id');

            if (Schema::hasTable('roles') && Schema::hasTable('permission_role')) {
                $roleIds = DB::table('roles')
                    ->whereIn('slug', ['super_admin', 'administrador', 'enfermeria', 'inspectoria', 'coordinador_inspectoria'])
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
                    foreach ($permissionIds as $permissionId) {
                        DB::table('permission_role')->insertOrIgnore([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            if (Schema::hasTable('roles') && Schema::hasTable('role_system_module')) {
                $moduleRoleMap = [
                    'infirmary_medical_leaves' => ['super_admin', 'administrador', 'enfermeria'],
                    'inspectoria_medical_leaves' => ['super_admin', 'administrador', 'inspectoria', 'coordinador_inspectoria'],
                ];

                foreach ($moduleRoleMap as $moduleSlug => $roleSlugs) {
                    $moduleId = DB::table('system_modules')->where('slug', $moduleSlug)->value('id');
                    foreach (DB::table('roles')->whereIn('slug', $roleSlugs)->pluck('id') as $roleId) {
                        DB::table('role_system_module')->insertOrIgnore([
                            'role_id' => $roleId,
                            'system_module_id' => $moduleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                foreach (DB::table('permission_groups')->whereIn('slug', ['enfermeria', 'inspectoria'])->pluck('id') as $groupId) {
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
};
