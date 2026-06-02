<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_work_orders', 'inventory_item_id')) {
                $table->foreignId('inventory_item_id')
                    ->nullable()
                    ->after('maintenance_dependency_id')
                    ->constrained('inventory_items')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_work_orders', 'inventory_item_id')) {
                $table->dropConstrainedForeignId('inventory_item_id');
            }
        });
    }
};

