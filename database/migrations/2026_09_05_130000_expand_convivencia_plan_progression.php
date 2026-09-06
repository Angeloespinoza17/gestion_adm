<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convivencia_plans', function (Blueprint $table): void {
            $table->foreignId('previous_plan_id')
                ->nullable()
                ->after('calendar_year')
                ->constrained('convivencia_plans')
                ->nullOnDelete();
            $table->index(['previous_plan_id', 'calendar_year'], 'conv_plans_previous_year_idx');
        });

        Schema::table('convivencia_plan_actions', function (Blueprint $table): void {
            $table->decimal('weight_percent', 5, 2)->default(0)->after('sort_order');
            $table->index(['plan_id', 'starts_on', 'ends_on'], 'conv_plan_actions_range_idx');
        });

        Schema::table('convivencia_plan_activities', function (Blueprint $table): void {
            $table->foreignId('activity_type_item_id')
                ->nullable()
                ->after('plan_action_id')
                ->constrained('convivencia_catalog_items')
                ->nullOnDelete();
            $table->string('activity_type_label', 160)->nullable()->after('activity_type_item_id');
            $table->index(
                ['activity_type_item_id', 'status', 'starts_at'],
                'conv_plan_activity_type_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('convivencia_plan_activities', function (Blueprint $table): void {
            $table->dropForeign(['activity_type_item_id']);
            $table->dropIndex('conv_plan_activity_type_status_idx');
            $table->dropColumn(['activity_type_item_id', 'activity_type_label']);
        });

        Schema::table('convivencia_plan_actions', function (Blueprint $table): void {
            $table->dropIndex('conv_plan_actions_range_idx');
            $table->dropColumn('weight_percent');
        });

        Schema::table('convivencia_plans', function (Blueprint $table): void {
            $table->dropForeign(['previous_plan_id']);
            $table->dropIndex('conv_plans_previous_year_idx');
            $table->dropColumn('previous_plan_id');
        });
    }
};
