<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->index(
                ['subject_type', 'course_section_id', 'attended_at'],
                'inf_attn_subject_course_date_idx',
            );
            $table->index(
                ['subject_type', 'attention_category', 'occurred_at'],
                'inf_attn_subject_category_occ_idx',
            );
        });

        Schema::table('infirmary_attention_calls', function (Blueprint $table) {
            $table->index(['called_at', 'call_status'], 'inf_call_date_status_idx');
        });

        Schema::table('infirmary_attention_follow_ups', function (Blueprint $table) {
            $table->index(['followed_at', 'status'], 'inf_follow_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('infirmary_attention_follow_ups', function (Blueprint $table) {
            $table->dropIndex('inf_follow_date_status_idx');
        });

        Schema::table('infirmary_attention_calls', function (Blueprint $table) {
            $table->dropIndex('inf_call_date_status_idx');
        });

        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->dropIndex('inf_attn_subject_category_occ_idx');
            $table->dropIndex('inf_attn_subject_course_date_idx');
        });
    }
};
