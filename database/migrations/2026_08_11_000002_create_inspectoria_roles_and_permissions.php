<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'ver_modulo_inspectoria' => 'Ver módulo de Inspectoría',
        'registrar_atenciones_inspectoria' => 'Registrar atenciones de Inspectoría',
        'asignar_cursos_inspectoria' => 'Asignar cursos a inspectoras',
        'gestionar_pases_inspectoria' => 'Gestionar pases prioritarios de Inspectoría',
        'ver_fichas_inspectoria' => 'Ver fichas de alumnas en Inspectoría',
        'registrar_bitacora_inspectoria' => 'Registrar bitácora diaria de Inspectoría',
    ];

    /** @var array<int, string> */
    private const INSPECTOR_PERMISSIONS = [
        'ver_modulo_inspectoria',
        'registrar_atenciones_inspectoria',
        'gestionar_pases_inspectoria',
        'ver_fichas_inspectoria',
        'registrar_bitacora_inspectoria',
    ];

    /** @var array<int, string> */
    private const MODULES = [
        'inspectoria',
        'inspectoria_atenciones',
        'inspectoria_asignaciones',
        'inspectoria_pases',
        'inspectoria_alumnas',
        'inspectoria_bitacora',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            $this->upsertRole(
                'inspectoria',
                'Inspector/a',
                'Atención operativa limitada a los cursos asignados.',
                $now,
            );
            $this->upsertRole(
                'coordinador_inspectoria',
                'Coordinador/a de Inspectoría',
                'Coordinación general de Inspectoría y administración de asignaciones.',
                $now,
            );

            if (Schema::hasTable('cargos')) {
                $this->upsertCargo(
                    'inspectoria',
                    'Inspector/a',
                    'Atención operativa de los cursos asignados.',
                    $now,
                );
                $this->upsertCargo(
                    'coordinador_inspectoria',
                    'Coordinador/a de Inspectoría',
                    'Coordinación general de Inspectoría y asignación de cursos.',
                    $now,
                );
            }

            foreach (self::PERMISSIONS as $slug => $name) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => 'Permiso operativo del módulo de Inspectoría.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('permissions')->where('slug', $slug)->update([
                    'name' => $name,
                    'description' => 'Permiso operativo del módulo de Inspectoría.',
                    'active' => true,
                    'updated_at' => $now,
                ]);
            }

            $inspectorRoleId = DB::table('roles')->where('slug', 'inspectoria')->value('id');
            $coordinatorRoleId = DB::table('roles')->where('slug', 'coordinador_inspectoria')->value('id');

            if (Schema::hasTable('permission_role')) {
                $permissionIds = DB::table('permissions')
                    ->whereIn('slug', array_keys(self::PERMISSIONS))
                    ->pluck('id', 'slug');

                if ($inspectorRoleId) {
                    $this->attachPermissions(
                        (int) $inspectorRoleId,
                        $permissionIds->only(self::INSPECTOR_PERMISSIONS)->values()->all(),
                        $now,
                    );
                }

                if ($coordinatorRoleId) {
                    $this->attachPermissions(
                        (int) $coordinatorRoleId,
                        $permissionIds->values()->all(),
                        $now,
                    );
                }
            }

            if (Schema::hasTable('system_modules') && Schema::hasTable('role_system_module')) {
                $moduleIds = DB::table('system_modules')
                    ->whereIn('slug', self::MODULES)
                    ->pluck('id')
                    ->all();

                foreach (array_filter([$inspectorRoleId, $coordinatorRoleId]) as $roleId) {
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
        // Cambio aditivo: los roles o cargos podrían estar asignados a usuarios y funcionarios.
    }

    private function upsertRole(string $slug, string $name, string $description, mixed $now): void
    {
        DB::table('roles')->insertOrIgnore([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('roles')->where('slug', $slug)->update([
            'name' => $name,
            'description' => $description,
            'active' => true,
            'updated_at' => $now,
        ]);
    }

    private function upsertCargo(string $slug, string $name, string $description, mixed $now): void
    {
        DB::table('cargos')->insertOrIgnore([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('cargos')->where('slug', $slug)->update([
            'name' => $name,
            'description' => $description,
            'active' => true,
            'updated_at' => $now,
        ]);
    }

    /** @param array<int, int|string> $permissionIds */
    private function attachPermissions(int $roleId, array $permissionIds, mixed $now): void
    {
        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
