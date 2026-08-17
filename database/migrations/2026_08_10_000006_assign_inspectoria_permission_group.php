<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'ver_modulo_inspectoria',
        'registrar_atenciones_inspectoria',
        'asignar_cursos_inspectoria',
        'gestionar_pases_inspectoria',
        'ver_fichas_inspectoria',
        'registrar_bitacora_inspectoria',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        $moduleId = DB::table('system_modules')->where('slug', 'inspectoria')->value('id');
        if (! $moduleId) {
            return;
        }

        $now = now();
        DB::table('permission_groups')->insertOrIgnore([
            'system_module_id' => $moduleId,
            'name' => 'Inspectoría',
            'slug' => 'inspectoria',
            'description' => 'Atenciones breves, asignación de cursos, pases prioritarios, fichas de alumnas y bitácora diaria.',
            'sort_order' => 195,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $groupId = DB::table('permission_groups')->where('slug', 'inspectoria')->value('id');
        foreach (DB::table('permissions')->whereIn('slug', self::PERMISSIONS)->pluck('id') as $permissionId) {
            DB::table('permission_group_permission')->insertOrIgnore([
                'permission_group_id' => $groupId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Catálogo aditivo: se conserva para no retirar permisos asignados.
    }
};
