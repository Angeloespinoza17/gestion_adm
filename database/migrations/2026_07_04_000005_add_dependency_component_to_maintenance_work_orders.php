<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_work_orders', 'dependency_component')) {
                $table->string('dependency_component')
                    ->nullable()
                    ->after('inventory_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_work_orders', 'dependency_component')) {
                $table->dropColumn('dependency_component');
            }
        });
    }
};
