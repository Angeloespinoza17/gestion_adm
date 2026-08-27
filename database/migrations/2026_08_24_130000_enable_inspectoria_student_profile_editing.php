<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'editar_fichas_inspectoria';

    public function up(): void
    {
        if (! Schema::hasTable('inspectoria_student_profile_change_logs')) {
            Schema::create('inspectoria_student_profile_change_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('student_profile_id')->nullable();
                $table->foreignId('actor_user_id')->nullable();
                $table->json('changed_fields');
                $table->char('ip_address_hash', 64)->nullable();
                $table->char('user_agent_hash', 64)->nullable();
                $table->dateTime('occurred_at');
                $table->timestamps();

                $table->index(['student_profile_id', 'occurred_at'], 'insp_student_profile_log_subject_idx');
                $table->index(['actor_user_id', 'occurred_at'], 'insp_student_profile_log_actor_idx');
                $table->foreign('student_profile_id', 'insp_student_profile_log_student_fk')
                    ->references('id')->on('student_profiles')->nullOnDelete();
                $table->foreign('actor_user_id', 'insp_student_profile_log_actor_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        // MySQL puede conservar la tabla vacía si falla un ALTER posterior al CREATE.
        // Esto permite reintentar la migración sin borrar la tabla ni sus eventuales datos.
        $this->ensureForeignKey(
            'student_profile_id',
            'student_profiles',
            'insp_student_profile_log_student_fk'
        );
        $this->ensureForeignKey(
            'actor_user_id',
            'users',
            'insp_student_profile_log_actor_fk'
        );

        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            DB::table('permissions')->insertOrIgnore([
                'slug' => self::PERMISSION,
                'name' => 'Editar datos de contacto en fichas de Inspectoría',
                'description' => 'Permite actualizar datos personales básicos y contactos de apoderados en alumnas dentro del alcance asignado.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('permissions')->where('slug', self::PERMISSION)->update([
                'name' => 'Editar datos de contacto en fichas de Inspectoría',
                'description' => 'Permite actualizar datos personales básicos y contactos de apoderados en alumnas dentro del alcance asignado.',
                'active' => true,
                'updated_at' => $now,
            ]);

            $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
            if (! $permissionId) {
                return;
            }

            if (Schema::hasTable('roles') && Schema::hasTable('permission_role')) {
                $roleIds = DB::table('roles')
                    ->whereIn('slug', ['inspectoria', 'coordinador_inspectoria', 'administrador', 'super_admin'])
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

    public function down(): void
    {
        // Migración aditiva y forward-only: no elimina auditoría ni retira permisos existentes.
    }

    private function ensureForeignKey(string $column, string $foreignTable, string $constraint): void
    {
        $exists = collect(Schema::getForeignKeys('inspectoria_student_profile_change_logs'))
            ->contains(fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'] ?? [], true));

        if ($exists) {
            return;
        }

        Schema::table('inspectoria_student_profile_change_logs', function (Blueprint $table) use ($column, $foreignTable, $constraint): void {
            $table->foreign($column, $constraint)
                ->references('id')
                ->on($foreignTable)
                ->nullOnDelete();
        });
    }
};
