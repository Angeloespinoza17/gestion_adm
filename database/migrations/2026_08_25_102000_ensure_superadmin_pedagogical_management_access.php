<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
                ['slug' => 'pedagogical_management'],
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
            $parentId = DB::table('system_modules')->where('slug', 'pedagogical_management')->value('id');
            DB::table('system_modules')->updateOrInsert(
                ['slug' => 'pedagogical_instrument_analysis'],
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

            $roleIds = Schema::hasTable('roles')
                ? DB::table('roles')->whereIn('slug', ['super_admin', 'superadmin'])->pluck('id')
                : collect();
            if (Schema::hasTable('permission_role')) {
                $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
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

            if (Schema::hasTable('role_system_module')) {
                $moduleIds = DB::table('system_modules')
                    ->whereIn('slug', ['pedagogical_management', 'pedagogical_instrument_analysis'])
                    ->pluck('id');
                foreach ($roleIds as $roleId) {
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
        // Forward-only: no elimina permisos, módulos ni asignaciones vigentes.
    }
};
