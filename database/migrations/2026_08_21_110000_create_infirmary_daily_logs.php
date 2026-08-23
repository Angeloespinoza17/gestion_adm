<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VIEW_PERMISSION = 'ver_bitacora_enfermeria';

    private const MANAGE_PERMISSION = 'registrar_bitacora_enfermeria';

    public function up(): void
    {
        if (! Schema::hasTable('infirmary_daily_logs')) {
            Schema::create('infirmary_daily_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
                $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
                $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('happened_at')->index();
                $table->string('category', 50)->index();
                $table->string('priority', 20)->default('media')->index();
                $table->string('status', 30)->default('registrado')->index();
                $table->string('title', 191);
                $table->text('detail');
                $table->text('action_taken')->nullable();
                $table->boolean('requires_follow_up')->default(false)->index();
                $table->text('follow_up_note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['student_profile_id', 'happened_at'], 'inf_log_student_date_idx');
                $table->index(['status', 'requires_follow_up', 'happened_at'], 'inf_log_follow_status_date_idx');
                $table->index(['category', 'happened_at'], 'inf_log_category_date_idx');
            });
        }

        $this->registerAccess();
    }

    public function down(): void
    {
        // Migración aditiva: la bitácora y sus registros se conservan íntegramente.
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
                    'name' => 'Ver bitácora de Enfermería',
                    'description' => 'Permite consultar la bitácora clínico-operativa de Enfermería.',
                ],
                self::MANAGE_PERMISSION => [
                    'name' => 'Registrar bitácora de Enfermería',
                    'description' => 'Permite crear y actualizar registros de la bitácora de Enfermería.',
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
            }

            $parentId = DB::table('system_modules')->where('slug', 'infirmary')->value('id');
            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'infirmary_daily_log',
                'name' => 'Bitácora diaria',
                'frontend_route' => '/infirmary/daily-log',
                'icon' => 'bx-notepad',
                'sort_order' => 9,
                'parent_id' => $parentId,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (Schema::hasTable('roles') && Schema::hasTable('permission_role')) {
                $permissionIds = DB::table('permissions')->whereIn('slug', array_keys($permissions))->pluck('id');
                $roleIds = DB::table('roles')->whereIn('slug', ['super_admin', 'administrador', 'enfermeria'])->pluck('id');

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
                $moduleId = DB::table('system_modules')->where('slug', 'infirmary_daily_log')->value('id');
                $roleIds = DB::table('roles')->whereIn('slug', ['super_admin', 'administrador', 'enfermeria'])->pluck('id');

                foreach ($roleIds as $roleId) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                $groupIds = DB::table('permission_groups')->where('slug', 'enfermeria')->pluck('id');
                $permissionIds = DB::table('permissions')->whereIn('slug', array_keys($permissions))->pluck('id');

                foreach ($groupIds as $groupId) {
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
