<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PARENT_MODULE = 'pedagogical_management';

    private const CHILD_MODULE = 'pedagogical_instrument_analysis';

    private const PERMISSIONS = [
        'pedagogical-instruments.view' => ['Ver instrumentos pedagógicos', 'Permite consultar instrumentos propios y sus análisis.'],
        'pedagogical-instruments.view-all' => ['Ver todos los instrumentos pedagógicos', 'Permite revisar instrumentos del establecimiento.'],
        'pedagogical-instruments.create' => ['Importar instrumentos pedagógicos', 'Permite registrar fichas e importar PDF de instrumentos.'],
        'pedagogical-instruments.update' => ['Editar instrumentos pedagógicos', 'Permite actualizar fichas técnicas autorizadas.'],
        'pedagogical-instruments.archive' => ['Archivar instrumentos pedagógicos', 'Permite archivar instrumentos conservando su trazabilidad.'],
        'pedagogical-instruments.download' => ['Descargar instrumentos pedagógicos', 'Permite visualizar y descargar PDF autorizados.'],
        'pedagogical-instruments.analyze' => ['Analizar instrumentos pedagógicos', 'Permite ejecutar y repetir análisis determinísticos.'],
        'pedagogical-instruments.resolve-validations' => ['Resolver observaciones pedagógicas', 'Permite justificar, resolver y reabrir resultados de validación.'],
        'pedagogical-instruments.approve' => ['Aprobar revisión de instrumentos', 'Permite realizar la aprobación institucional posterior a la revisión.'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            foreach (self::PERMISSIONS as $slug => [$name, $description]) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => $name, 'description' => $description, 'active' => true, 'updated_at' => $now, 'created_at' => $now],
                );
            }

            DB::table('system_modules')->updateOrInsert(
                ['slug' => self::PARENT_MODULE],
                [
                    'name' => 'Gestión pedagógica',
                    'frontend_route' => null,
                    'icon' => 'bx-book-content',
                    'sort_order' => 22,
                    'active' => true,
                    'parent_id' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
            $parentId = DB::table('system_modules')->where('slug', self::PARENT_MODULE)->value('id');
            DB::table('system_modules')->updateOrInsert(
                ['slug' => self::CHILD_MODULE],
                [
                    'name' => 'Análisis de instrumentos',
                    'frontend_route' => '/gestion-pedagogica/analisis-instrumentos',
                    'icon' => 'bx-file-find',
                    'sort_order' => 1,
                    'active' => true,
                    'parent_id' => $parentId,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id', 'slug');
            $moduleIds = DB::table('system_modules')->whereIn('slug', [self::PARENT_MODULE, self::CHILD_MODULE])->pluck('id');
            $fullRoleSlugs = ['super_admin', 'administrador', 'direccion', 'coordinador_academico', 'jefe_utp'];
            $teacherPermissions = [
                'pedagogical-instruments.view',
                'pedagogical-instruments.create',
                'pedagogical-instruments.update',
                'pedagogical-instruments.download',
                'pedagogical-instruments.analyze',
            ];
            $roles = DB::table('roles')->whereIn('slug', [...$fullRoleSlugs, 'docente'])->get(['id', 'slug']);

            if (Schema::hasTable('permission_role')) {
                foreach ($roles as $role) {
                    $slugs = in_array($role->slug, $fullRoleSlugs, true) ? array_keys(self::PERMISSIONS) : $teacherPermissions;
                    foreach ($slugs as $slug) {
                        $permissionId = $permissionIds[$slug] ?? null;
                        if ($permissionId) {
                            DB::table('permission_role')->insertOrIgnore([
                                'role_id' => $role->id,
                                'permission_id' => $permissionId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            }

            if (Schema::hasTable('role_system_module')) {
                foreach ($roles as $role) {
                    foreach ($moduleIds as $moduleId) {
                        DB::table('role_system_module')->insertOrIgnore([
                            'role_id' => $role->id,
                            'system_module_id' => $moduleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                DB::table('permission_groups')->updateOrInsert(
                    ['slug' => 'gestion_pedagogica'],
                    [
                        'system_module_id' => $parentId,
                        'name' => 'Gestión pedagógica',
                        'description' => 'Permisos para revisión determinística de instrumentos de evaluación.',
                        'sort_order' => 1,
                        'active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
                $groupId = DB::table('permission_groups')->where('slug', 'gestion_pedagogica')->value('id');
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_group_permission')->insertOrIgnore([
                        'permission_group_id' => $groupId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Forward-only en producción: no se eliminan permisos, módulos ni asignaciones vigentes.
    }
};
