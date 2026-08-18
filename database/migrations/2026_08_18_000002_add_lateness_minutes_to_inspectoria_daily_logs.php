<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspectoria_daily_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('lateness_minutes')->nullable()->after('late_staff_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('inspectoria_daily_logs', function (Blueprint $table) {
            $table->dropColumn('lateness_minutes');
        });
    }
};
