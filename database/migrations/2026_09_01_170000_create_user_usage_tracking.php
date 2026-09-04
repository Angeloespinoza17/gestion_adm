<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_usage_daily')) {
            Schema::create('user_usage_daily', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('usage_date');
                $table->unsignedInteger('login_count')->default(0);
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamp('first_activity_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'usage_date'], 'user_usage_daily_user_date_uq');
                $table->index(['usage_date', 'last_activity_at'], 'user_usage_daily_date_activity_idx');
            });
        }

        if (! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'superadmin',
                'name' => 'Superadmin',
                'frontend_route' => null,
                'icon' => 'bx-lock-alt',
                'sort_order' => 118,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $parentId = DB::table('system_modules')->where('slug', 'superadmin')->value('id');
            if (! $parentId) {
                return;
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'superadmin_usage_level',
                'name' => 'Nivel de uso',
                'frontend_route' => '/superadmin/nivel-uso',
                'icon' => null,
                'sort_order' => 4,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! Schema::hasTable('roles') || ! Schema::hasTable('role_system_module')) {
                return;
            }

            $roleId = DB::table('roles')->where('slug', 'super_admin')->value('id');
            $moduleIds = DB::table('system_modules')
                ->whereIn('slug', ['superadmin', 'superadmin_usage_level'])
                ->pluck('id');

            foreach ($moduleIds as $moduleId) {
                if ($roleId) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Migración aditiva: un rollback no elimina métricas ni asignaciones RBAC ya registradas.
    }
};
