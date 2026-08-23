<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psychology_activities', function (Blueprint $table) {
            $table->index(
                ['next_action_on', 'case_id'],
                'psychology_activity_followup_date_case_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('psychology_activities', function (Blueprint $table) {
            $table->dropIndex('psychology_activity_followup_date_case_idx');
        });
    }
};
