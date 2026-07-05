<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_annual_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_annual_plans', 'item_type')) {
                $table->string('item_type', 40)
                    ->default('dependency')
                    ->after('maintenance_dependency_id')
                    ->index('map_item_type_idx');
            }

            if (!Schema::hasColumn('maintenance_annual_plans', 'inventory_item_id')) {
                $table->foreignId('inventory_item_id')
                    ->nullable()
                    ->after('item_type')
                    ->constrained('inventory_items')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('maintenance_annual_plans', 'technical_area_id')) {
                $table->foreignId('technical_area_id')
                    ->nullable()
                    ->after('inventory_item_id')
                    ->constrained('maintenance_dependencies')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('maintenance_annual_plans', 'component_name')) {
                $table->string('component_name')
                    ->nullable()
                    ->after('technical_area_id');
            }

            if (!Schema::hasColumn('maintenance_annual_plans', 'last_maintenance_date')) {
                $table->date('last_maintenance_date')
                    ->nullable()
                    ->after('completed_date');
            }

            if (!Schema::hasColumn('maintenance_annual_plans', 'alert_days')) {
                $table->unsignedSmallInteger('alert_days')
                    ->default(30)
                    ->after('last_maintenance_date');
            }

            if (!Schema::hasColumn('maintenance_annual_plans', 'alert_enabled')) {
                $table->boolean('alert_enabled')
                    ->default(true)
                    ->after('alert_days');
            }

            $table->index(['scheduled_date', 'status'], 'map_scheduled_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_annual_plans', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_annual_plans', 'scheduled_date')) {
                $table->dropIndex('map_scheduled_status_idx');
            }

            if (Schema::hasColumn('maintenance_annual_plans', 'alert_enabled')) {
                $table->dropColumn('alert_enabled');
            }

            if (Schema::hasColumn('maintenance_annual_plans', 'alert_days')) {
                $table->dropColumn('alert_days');
            }

            if (Schema::hasColumn('maintenance_annual_plans', 'last_maintenance_date')) {
                $table->dropColumn('last_maintenance_date');
            }

            if (Schema::hasColumn('maintenance_annual_plans', 'component_name')) {
                $table->dropColumn('component_name');
            }

            if (Schema::hasColumn('maintenance_annual_plans', 'technical_area_id')) {
                $table->dropConstrainedForeignId('technical_area_id');
            }

            if (Schema::hasColumn('maintenance_annual_plans', 'inventory_item_id')) {
                $table->dropConstrainedForeignId('inventory_item_id');
            }

            if (Schema::hasColumn('maintenance_annual_plans', 'item_type')) {
                $table->dropIndex('map_item_type_idx');
                $table->dropColumn('item_type');
            }
        });
    }
};
