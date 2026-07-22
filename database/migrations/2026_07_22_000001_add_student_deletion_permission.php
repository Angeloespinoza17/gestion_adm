<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'slug' => 'eliminar_estudiantes',
            'name' => 'Eliminar Estudiantes',
            'description' => 'Permite revisar el impacto y eliminar de forma definitiva la ficha de una estudiante junto con su cuenta y registros dependientes.',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permissions')
            ->where('slug', 'eliminar_estudiantes')
            ->update([
                'name' => 'Eliminar Estudiantes',
                'description' => 'Permite revisar el impacto y eliminar de forma definitiva la ficha de una estudiante junto con su cuenta y registros dependientes.',
                'active' => true,
                'updated_at' => now(),
            ]);

        $permissionId = DB::table('permissions')->where('slug', 'eliminar_estudiantes')->value('id');

        if (! $permissionId) {
            return;
        }

        if (Schema::hasTable('roles') && Schema::hasTable('permission_role')) {
            $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

            if ($superAdminRoleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $superAdminRoleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
            $studentGroupId = DB::table('permission_groups')->where('slug', 'estudiantes')->value('id');

            if ($studentGroupId) {
                DB::table('permission_group_permission')->insertOrIgnore([
                    'permission_group_id' => $studentGroupId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // El permiso es aditivo. No se eliminan asignaciones RBAC durante rollbacks.
    }
};
