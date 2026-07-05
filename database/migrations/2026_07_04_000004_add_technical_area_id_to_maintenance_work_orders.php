<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_work_orders', 'technical_area_id')) {
                $table->foreignId('technical_area_id')
                    ->nullable()
                    ->after('maintenance_dependency_id')
                    ->constrained('maintenance_dependencies')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_work_orders', 'technical_area_id')) {
                $table->dropConstrainedForeignId('technical_area_id');
            }
        });
    }
};
