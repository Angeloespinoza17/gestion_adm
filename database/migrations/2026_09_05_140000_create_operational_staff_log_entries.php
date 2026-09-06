<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'operational_logbook.view' => 'Ver bitácora operativa personal',
        'operational_logbook.create' => 'Registrar y corregir bitácora operativa personal',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('operational_staff_log_entries')) {
            Schema::create('operational_staff_log_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
                $table->string('owner_name_snapshot');
                $table->dateTime('occurred_at');
                $table->string('category', 40);
                $table->string('custom_category', 80)->nullable();
                $table->string('title', 180);
                $table->text('details');
                $table->timestamps();

                $table->index(['owner_user_id', 'occurred_at'], 'op_staff_log_owner_date_idx');
                $table->index(['category', 'occurred_at'], 'op_staff_log_category_date_idx');
                $table->index('occurred_at', 'op_staff_log_occurred_at_idx');
            });
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            foreach (self::PERMISSIONS as $slug => $name) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => 'Acceso privado al módulo independiente de bitácora de funcionarios.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'operational_staff_logbook',
                'name' => 'Bitácora',
                'frontend_route' => '/bitacora',
                'icon' => 'bx-notepad',
                'sort_order' => 41,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $moduleId = DB::table('system_modules')->where('slug', 'operational_staff_logbook')->value('id');
            if (! $moduleId || ! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
                return;
            }

            DB::table('permission_groups')->insertOrIgnore([
                'slug' => 'operational_staff_logbook',
                'system_module_id' => $moduleId,
                'name' => 'Bitácora de funcionarios',
                'description' => 'Registro personal de funcionarios y consulta institucional protegida.',
                'sort_order' => 256,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $groupId = DB::table('permission_groups')->where('slug', 'operational_staff_logbook')->value('id');
            if (! $groupId) {
                return;
            }

            foreach (DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id') as $permissionId) {
                DB::table('permission_group_permission')->insertOrIgnore([
                    'permission_group_id' => $groupId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Se preservan las bitácoras y sus asignaciones RBAC para evitar pérdida de trazabilidad.
    }
};
