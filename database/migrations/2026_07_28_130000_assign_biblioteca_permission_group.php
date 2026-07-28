<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('system_modules')
            || ! Schema::hasTable('permission_groups')
            || ! Schema::hasTable('permission_group_permission')
        ) {
            return;
        }

        $moduleId = DB::table('system_modules')->where('slug', 'biblioteca')->value('id');

        if (! $moduleId) {
            return;
        }

        $now = now();

        DB::table('permission_groups')->insertOrIgnore([
            'system_module_id' => $moduleId,
            'name' => 'Biblioteca',
            'slug' => 'biblioteca',
            'description' => 'Catálogo, inventario, préstamos, devoluciones, reservas, textos escolares, materiales, pases, espacios y reportes.',
            'sort_order' => 200,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permission_groups')
            ->where('slug', 'biblioteca')
            ->update([
                'system_module_id' => $moduleId,
                'name' => 'Biblioteca',
                'description' => 'Catálogo, inventario, préstamos, devoluciones, reservas, textos escolares, materiales, pases, espacios y reportes.',
                'sort_order' => 200,
                'active' => true,
                'updated_at' => $now,
            ]);

        $groupId = DB::table('permission_groups')->where('slug', 'biblioteca')->value('id');

        if (! $groupId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'gestionar_categorias_biblioteca',
                'gestionar_almacenaje_biblioteca',
                'gestionar_textos_escolares_biblioteca',
                'gestionar_materiales_biblioteca',
                'gestionar_pases_biblioteca',
            ])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
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
        // La relación forma parte del catálogo permanente de permisos.
        // Se conserva para evitar retirar accesos durante un rollback.
    }
};
