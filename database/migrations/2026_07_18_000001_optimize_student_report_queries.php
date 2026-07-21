<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->index(
                ['academic_year_id', 'course_section_id', 'enrollment_status'],
                'stud_enroll_report_scope_idx',
            );
        });

        Schema::table('student_enrollment_movements', function (Blueprint $table) {
            $table->index(
                ['academic_year_id', 'effective_date', 'movement_type'],
                'stud_mov_report_period_idx',
            );
        });

        Schema::table('student_promotions', function (Blueprint $table) {
            $table->index(
                ['from_academic_year_id', 'from_course_section_id', 'promotion_status'],
                'stud_promo_report_scope_idx',
            );
        });

        Schema::table('infirmary_accidents', function (Blueprint $table) {
            $table->index(
                ['student_profile_id', 'occurred_at'],
                'inf_acc_student_date_idx',
            );
        });

        Schema::table('infirmary_attention_referrals', function (Blueprint $table) {
            $table->index(
                ['referred_at', 'attention_id'],
                'inf_ref_date_attention_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('infirmary_attention_referrals', function (Blueprint $table) {
            $table->dropIndex('inf_ref_date_attention_idx');
        });

        Schema::table('infirmary_accidents', function (Blueprint $table) {
            $table->dropIndex('inf_acc_student_date_idx');
        });

        Schema::table('student_promotions', function (Blueprint $table) {
            $table->dropIndex('stud_promo_report_scope_idx');
        });

        Schema::table('student_enrollment_movements', function (Blueprint $table) {
            $table->dropIndex('stud_mov_report_period_idx');
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropIndex('stud_enroll_report_scope_idx');
        });
    }
};
