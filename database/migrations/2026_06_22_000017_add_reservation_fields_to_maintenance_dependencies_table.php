<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_dependencies', function (Blueprint $table) {
            $table->foreignId('dependency_type_id')->nullable()->after('id')->constrained('dependency_types')->nullOnDelete();
            $table->foreignId('responsible_staff_id')->nullable()->after('active')->constrained('staff')->nullOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->string('location')->nullable()->after('description');
            $table->string('floor_sector')->nullable()->after('location');
            $table->unsignedInteger('capacity_max')->nullable()->after('floor_sector');
            $table->text('available_equipment')->nullable()->after('capacity_max');
            $table->string('availability_status', 30)->default('disponible')->after('available_equipment');
            $table->text('observations')->nullable()->after('notes');
            $table->string('image_path')->nullable()->after('observations');
            $table->string('calendar_color', 20)->nullable()->after('image_path');
            $table->boolean('requires_approval')->default(false)->after('calendar_color');

            $table->index(['dependency_type_id', 'availability_status'], 'maintenance_dependencies_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_dependencies', function (Blueprint $table) {
            $table->dropIndex('maintenance_dependencies_type_status_idx');
            $table->dropConstrainedForeignId('dependency_type_id');
            $table->dropConstrainedForeignId('responsible_staff_id');
            $table->dropColumn([
                'description',
                'location',
                'floor_sector',
                'capacity_max',
                'available_equipment',
                'availability_status',
                'observations',
                'image_path',
                'calendar_color',
                'requires_approval',
            ]);
        });
    }
};
