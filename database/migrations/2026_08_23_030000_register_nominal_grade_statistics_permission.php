<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'grade_statistics.view_students';

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            DB::table('permissions')->insertOrIgnore([
                'slug' => self::PERMISSION,
                'name' => 'Ver consolidado nominal de calificaciones',
                'description' => 'Permite consultar el detalle de calificaciones consolidado por alumna y curso.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('permissions')->where('slug', self::PERMISSION)->update([
                'name' => 'Ver consolidado nominal de calificaciones',
                'description' => 'Permite consultar el detalle de calificaciones consolidado por alumna y curso.',
                'active' => true,
                'updated_at' => $now,
            ]);

            $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
            if (! $permissionId) {
                return;
            }

            if (Schema::hasTable('permission_role')) {
                $roleIds = DB::table('roles')
                    ->whereIn('slug', ['super_admin', 'administrador', 'direccion', 'coordinador_academico'])
                    ->pluck('id');
                foreach ($roleIds as $roleId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                $groupId = DB::table('permission_groups')->where('slug', 'estudiantes')->value('id');
                if ($groupId) {
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
        // Forward-only: no retira accesos ni elimina configuración RBAC existente.
    }
};
