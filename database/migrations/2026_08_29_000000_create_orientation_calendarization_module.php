<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orientation_calendarization_entries')) {
            Schema::create('orientation_calendarization_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('orientation_plan_id');
                $table->unsignedBigInteger('orientation_action_id')->nullable();
                $table->string('level_group', 40);
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('category', 40)->default('activity');
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 30)->default('planned');
                $table->string('source_key', 120)->nullable();
                $table->string('source_label', 191)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('orientation_plan_id', 'orientation_cal_plan_fk')
                    ->references('id')->on('orientation_plans')->cascadeOnDelete();
                $table->foreign('orientation_action_id', 'orientation_cal_action_fk')
                    ->references('id')->on('orientation_actions')->nullOnDelete();
                $table->foreign('created_by', 'orientation_cal_created_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'orientation_cal_updated_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->unique(['orientation_plan_id', 'source_key'], 'orientation_calendarization_source_unique');
                $table->index(
                    ['orientation_plan_id', 'level_group', 'start_date', 'status'],
                    'orientation_calendarization_plan_layer_idx'
                );
                $table->index(['orientation_action_id', 'start_date'], 'orientation_calendarization_action_idx');
            });
        }

        $this->registerNavigation();
    }

    public function down(): void
    {
        // Migración aditiva: no elimina calendarizaciones ni retira accesos en producción.
    }

    private function registerNavigation(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $parentId = DB::table('system_modules')->where('slug', 'orientation')->value('id');
            if (! $parentId) {
                return;
            }

            $now = now();
            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'orientation_calendarization',
                'name' => 'Calendarización',
                'frontend_route' => '/orientation/calendarizacion',
                'icon' => null,
                'sort_order' => 2,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! Schema::hasTable('role_system_module')) {
                return;
            }

            $moduleId = DB::table('system_modules')->where('slug', 'orientation_calendarization')->value('id');
            $roleIds = DB::table('roles')
                ->whereIn('slug', ['orientacion', 'super_admin', 'superadmin'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $roleId,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
};
