<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_coexistence_entries')) {
            Schema::create('lcd_coexistence_entries', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->nullable()->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->restrictOnDelete();
                $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
                $table->foreignId('convivencia_case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
                $table->foreignId('convivencia_daily_log_id')->nullable()->constrained('convivencia_daily_logs')->nullOnDelete();
                $table->string('entry_type', 80);
                $table->string('category_code', 80)->nullable();
                $table->dateTime('happened_at');
                $table->string('place_snapshot', 160)->nullable();
                $table->string('student_name_snapshot')->nullable();
                $table->string('course_snapshot')->nullable();
                $table->longText('description_encrypted');
                $table->longText('immediate_action_encrypted')->nullable();
                $table->string('confidentiality_level', 40)->default('restricted');
                $table->boolean('guardian_informed')->default(false);
                $table->string('status', 40)->default('recorded');
                $table->json('evidence')->nullable();
                $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('revision')->default(1);
                $table->timestamps();

                $table->index(['book_id', 'happened_at', 'status'], 'lcd_coexistence_book_date_idx');
                $table->index(['student_profile_id', 'happened_at'], 'lcd_coexistence_student_date_idx');
                $table->index(['entry_type', 'category_code'], 'lcd_coexistence_type_category_idx');
            });
        }

        if (! Schema::hasTable('lcd_pie_support_records')) {
            Schema::create('lcd_pie_support_records', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->nullable()->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->restrictOnDelete();
                $table->foreignId('apoyo_atencion_id')->nullable()->constrained('apoyo_atenciones')->nullOnDelete();
                $table->foreignId('apoyo_plan_id')->nullable()->constrained('apoyo_planes')->nullOnDelete();
                $table->foreignId('professional_staff_id')->constrained('staff')->restrictOnDelete();
                $table->string('record_type', 80);
                $table->dateTime('recorded_at');
                $table->unsignedSmallInteger('duration_minutes')->nullable();
                $table->string('student_name_snapshot');
                $table->string('professional_name_snapshot');
                $table->text('objective_encrypted')->nullable();
                $table->longText('details_encrypted');
                $table->longText('agreements_encrypted')->nullable();
                $table->string('confidentiality_level', 40)->default('confidential');
                $table->string('status', 40)->default('recorded');
                $table->json('evidence')->nullable();
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['student_profile_id', 'recorded_at'], 'lcd_pie_records_student_date_idx');
                $table->index(['book_id', 'record_type', 'status'], 'lcd_pie_records_book_type_idx');
            });
        }

        if (! Schema::hasTable('lcd_absence_cases')) {
            Schema::create('lcd_absence_cases', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
                $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
                $table->foreignId('attendance_intervention_id')->nullable()->constrained('attendance_interventions')->nullOnDelete();
                $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('case_number', 100);
                $table->date('detected_on');
                $table->date('absence_started_on')->nullable();
                $table->date('last_absence_on')->nullable();
                $table->unsignedInteger('consecutive_absences')->default(0);
                $table->unsignedInteger('accumulated_absences')->default(0);
                $table->string('risk_level', 40)->default('monitoring');
                $table->string('status', 40)->default('open');
                $table->date('next_deadline_on')->nullable();
                $table->text('normative_basis')->nullable();
                $table->string('student_name_snapshot');
                $table->string('course_snapshot');
                $table->json('initial_snapshot')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('closure_reason')->nullable();
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'academic_year_id', 'case_number'], 'lcd_absence_cases_school_year_number_uq');
                $table->index(['student_profile_id', 'status', 'detected_on'], 'lcd_absence_cases_student_status_idx');
                $table->index(['school_id', 'status', 'next_deadline_on'], 'lcd_absence_cases_deadline_idx');
            });
        }

        if (! Schema::hasTable('lcd_absence_case_actions')) {
            Schema::create('lcd_absence_case_actions', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('absence_case_id')->constrained('lcd_absence_cases')->restrictOnDelete();
                $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action_type', 80);
                $table->timestamp('occurred_at')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->string('medium', 60)->nullable();
                $table->string('result_code', 80)->nullable();
                $table->longText('notes_encrypted')->nullable();
                $table->json('evidence')->nullable();
                $table->text('normative_basis')->nullable();
                $table->date('next_deadline_on')->nullable();
                $table->string('status', 40)->default('completed');
                $table->timestamps();

                $table->index(['absence_case_id', 'occurred_at'], 'lcd_absence_actions_case_date_idx');
                $table->index(['status', 'scheduled_at'], 'lcd_absence_actions_schedule_idx');
            });
        }

        if (! Schema::hasTable('lcd_parvularia_plans')) {
            Schema::create('lcd_parvularia_plans', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('assessment_period_id')->nullable()->constrained('lcd_assessment_periods')->restrictOnDelete();
                $table->foreignId('responsible_staff_id')->constrained('staff')->restrictOnDelete();
                $table->string('plan_type', 60);
                $table->string('horizon', 60)->default('weekly');
                $table->string('title');
                $table->string('curricular_scope', 120)->nullable();
                $table->string('curricular_nucleus', 120)->nullable();
                $table->longText('learning_experience');
                $table->longText('pedagogical_strategies')->nullable();
                $table->longText('resources')->nullable();
                $table->longText('environment_organization')->nullable();
                $table->longText('evaluation_strategy')->nullable();
                $table->longText('diversification_adjustments')->nullable();
                $table->json('responsibles')->nullable();
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('status', 40)->default('draft');
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['teaching_group_id', 'starts_on', 'ends_on'], 'lcd_parv_plans_group_dates_idx');
                $table->index(['book_id', 'status'], 'lcd_parv_plans_book_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_parvularia_plan_objectives')) {
            Schema::create('lcd_parvularia_plan_objectives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parvularia_plan_id')->constrained('lcd_parvularia_plans')->restrictOnDelete();
                $table->foreignId('learning_objective_id')->nullable()->constrained('lcd_learning_objectives')->nullOnDelete();
                $table->string('objective_code_snapshot', 100)->nullable();
                $table->text('objective_description_snapshot');
                $table->string('objective_type', 30)->default('OA');
                $table->timestamps();

                $table->index(['parvularia_plan_id', 'objective_type'], 'lcd_parv_plan_objectives_type_idx');
            });
        }

        if (! Schema::hasTable('lcd_parvularia_evaluations')) {
            Schema::create('lcd_parvularia_evaluations', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('parvularia_plan_id')->nullable()->constrained('lcd_parvularia_plans')->restrictOnDelete();
                $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->restrictOnDelete();
                $table->foreignId('learning_objective_id')->nullable()->constrained('lcd_learning_objectives')->nullOnDelete();
                $table->foreignId('evaluator_staff_id')->constrained('staff')->restrictOnDelete();
                $table->string('evaluation_type', 60);
                $table->string('scope', 40)->default('individual');
                $table->dateTime('observed_at');
                $table->string('indicator_snapshot')->nullable();
                $table->string('achievement_level', 80)->nullable();
                $table->longText('observation_encrypted');
                $table->longText('analysis_encrypted')->nullable();
                $table->longText('feedback_encrypted')->nullable();
                $table->longText('pedagogical_decision_encrypted')->nullable();
                $table->json('evidence')->nullable();
                $table->string('confidentiality_level', 40)->default('restricted');
                $table->string('status', 40)->default('recorded');
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['teaching_group_id', 'observed_at'], 'lcd_parv_evaluations_group_date_idx');
                $table->index(['student_profile_id', 'observed_at'], 'lcd_parv_evaluations_student_date_idx');
                $table->index(['book_id', 'status'], 'lcd_parv_evaluations_book_status_idx');
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: production records must never be removed by rollback.
    }
};
