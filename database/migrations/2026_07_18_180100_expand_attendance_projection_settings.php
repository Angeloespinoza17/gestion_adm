<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_projection_settings', function (Blueprint $table) {
            $table->decimal('target_attendance_rate', 5, 2)->default(85)->after('attendance_factor');
            $table->decimal('conservative_delta', 5, 2)->default(5)->after('target_attendance_rate');
            $table->decimal('custom_attendance_rate', 5, 2)->default(90)->after('conservative_delta');
            $table->decimal('additional_adjustments', 16, 4)->default(0)->after('custom_attendance_rate');
            $table->string('calculation_window', 40)->default('current_month')->after('annual_school_days');
            $table->date('valid_from')->nullable()->after('calculation_window');
            $table->date('valid_to')->nullable()->after('valid_from');
            $table->string('configuration_source')->nullable()->after('valid_to');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_projection_settings', function (Blueprint $table) {
            $table->dropColumn([
                'target_attendance_rate', 'conservative_delta', 'custom_attendance_rate',
                'additional_adjustments', 'calculation_window', 'valid_from', 'valid_to',
                'configuration_source',
            ]);
        });
    }
};
