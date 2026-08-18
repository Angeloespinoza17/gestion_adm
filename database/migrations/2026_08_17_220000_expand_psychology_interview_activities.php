<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psychology_activities', function (Blueprint $table) {
            $table->unsignedSmallInteger('interview_number')->nullable()->after('type');
            $table->json('participant_types')->nullable()->after('participants');
            $table->string('interviewee_type', 40)->nullable()->after('participant_types');
            $table->string('interviewee_name')->nullable()->after('interviewee_type');
            $table->text('interviewee_rut')->nullable()->after('interviewee_name');
            $table->string('interviewer_name_snapshot')->nullable()->after('interviewee_rut');
            $table->string('interviewer_position_snapshot', 160)->nullable()->after('interviewer_name_snapshot');
            $table->longText('general_background')->nullable()->after('private_note');
            $table->dateTime('next_interview_at')->nullable()->after('next_action_on');
            $table->string('acknowledgement_status', 30)->default('not_requested')->after('next_interview_at');
            $table->string('acknowledged_name')->nullable()->after('acknowledgement_status');
            $table->text('acknowledged_rut')->nullable()->after('acknowledged_name');
            $table->dateTime('acknowledged_at')->nullable()->after('acknowledged_rut');
            $table->text('acknowledgement_observations')->nullable()->after('acknowledged_at');
            $table->index(['case_id', 'interview_number'], 'psychology_activity_interview_number_idx');
            $table->index('next_interview_at', 'psychology_activity_next_interview_idx');
        });
    }

    public function down(): void
    {
        Schema::table('psychology_activities', function (Blueprint $table) {
            $table->dropIndex('psychology_activity_interview_number_idx');
            $table->dropIndex('psychology_activity_next_interview_idx');
            $table->dropColumn([
                'interview_number',
                'participant_types',
                'interviewee_type',
                'interviewee_name',
                'interviewee_rut',
                'interviewer_name_snapshot',
                'interviewer_position_snapshot',
                'general_background',
                'next_interview_at',
                'acknowledgement_status',
                'acknowledged_name',
                'acknowledged_rut',
                'acknowledged_at',
                'acknowledgement_observations',
            ]);
        });
    }
};
