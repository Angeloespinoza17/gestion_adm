<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->boolean('is_disseminable')
                ->default(true)
                ->after('requires_approval');

            $table->index(
                ['is_disseminable', 'start_date', 'end_date'],
                'calendar_events_home_dissemination_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex('calendar_events_home_dissemination_idx');
            $table->dropColumn('is_disseminable');
        });
    }
};
