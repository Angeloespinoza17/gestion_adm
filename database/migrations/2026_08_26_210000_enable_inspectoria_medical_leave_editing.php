<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EDIT_PERMISSION = 'editar_licencias_medicas_estudiantes';

    public function up(): void
    {
        $this->addUpdateAudit();
        $this->registerEditAccess();
    }

    public function down(): void
    {
        // Migración aditiva: no se eliminan auditorías, permisos ni asignaciones existentes.
    }

    private function addUpdateAudit(): void
    {
        if (! Schema::hasTable('student_medical_certificates')
            || Schema::hasColumn('student_medical_certificates', 'updated_by')) {
            return;
        }

        Schema::table('student_medical_certificates', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    private function registerEditAccess(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            DB::table('permissions')->insertOrIgnore([
                'slug' => self::EDIT_PERMISSION,
                'name' => 'Editar licencias médicas de estudiantes',
                'description' => 'Permite corregir los datos y adjuntar o reemplazar el respaldo privado de una licencia médica.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $permissionId = DB::table('permissions')->where('slug', self::EDIT_PERMISSION)->value('id');
            if (! $permissionId) {
                return;
            }

            if (Schema::hasTable('roles') && Schema::hasTable('permission_role')) {
                $roleIds = DB::table('roles')
                    ->whereIn('slug', ['super_admin', 'administrador', 'inspectoria', 'coordinador_inspectoria'])
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
                $groupId = DB::table('permission_groups')->where('slug', 'inspectoria')->value('id');
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
};
