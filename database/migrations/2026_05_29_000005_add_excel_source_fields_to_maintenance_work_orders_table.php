<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            $table->string('source_key')->nullable()->unique()->after('id');
            $table->string('location_code')->nullable()->after('maintenance_dependency_id');
            $table->string('location_distribution')->nullable()->after('location_code');
            $table->string('location_sector')->nullable()->after('location_distribution');
            $table->string('location_name')->nullable()->after('location_sector');
            $table->string('location_usage')->nullable()->after('location_name');
            $table->text('photo_reference')->nullable()->after('resolution_notes');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            $table->dropUnique(['source_key']);
            $table->dropColumn([
                'source_key',
                'location_code',
                'location_distribution',
                'location_sector',
                'location_name',
                'location_usage',
                'photo_reference',
            ]);
        });
    }
};
