<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('attendance_management_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->restrictOnDelete();
            $table->json('risk_thresholds');
            $table->json('risk_weights');
            $table->json('pattern_settings');
            $table->json('alert_settings');
            $table->json('recovery_settings');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('academic_year_id', 'att_mgmt_settings_year_uq');
        });

        $this->create('attendance_risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->date('snapshot_date');
            $table->unsignedSmallInteger('school_days_elapsed')->default(0);
            $table->unsignedSmallInteger('days_present')->default(0);
            $table->unsignedSmallInteger('days_absent')->default(0);
            $table->unsignedSmallInteger('justified_absences')->default(0);
            $table->unsignedSmallInteger('unjustified_absences')->default(0);
            $table->unsignedSmallInteger('late_arrivals')->default(0);
            $table->unsignedSmallInteger('early_departures')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->nullable();
            $table->decimal('attendance_last_30_days', 5, 2)->nullable();
            $table->decimal('attendance_last_15_days', 5, 2)->nullable();
            $table->string('risk_level', 30)->default('no_data');
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('trend', 30)->default('insufficient_data');
            $table->decimal('trend_points', 6, 2)->nullable();
            $table->unsignedSmallInteger('consecutive_absences')->default(0);
            $table->json('risk_reasons')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->unique(['student_profile_id', 'academic_year_id', 'snapshot_date'], 'att_snapshot_student_year_date_uq');
            $table->index(['academic_year_id', 'risk_level', 'risk_score'], 'att_snapshot_risk_idx');
            $table->index(['course_section_id', 'snapshot_date'], 'att_snapshot_course_date_idx');
        });

        $this->create('attendance_pattern_detections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->string('pattern_type', 80);
            $table->string('severity', 20)->default('info');
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->string('confidence_label', 30)->default('possible');
            $table->unsignedSmallInteger('occurrence_count')->default(0);
            $table->text('description');
            $table->json('metrics')->nullable();
            $table->json('period')->nullable();
            $table->dateTime('first_detected_at');
            $table->dateTime('last_detected_at');
            $table->boolean('is_active')->default(true);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['student_profile_id', 'academic_year_id', 'pattern_type'], 'att_pattern_student_year_type_uq');
            $table->index(['academic_year_id', 'is_active', 'severity'], 'att_pattern_scope_idx');
            $table->index(['course_section_id', 'is_active'], 'att_pattern_course_idx');
        });

        $this->create('attendance_intervention_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->string('category', 80)->nullable();
            $table->boolean('family_contact')->default(false);
            $table->boolean('sensitive')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'sort_order'], 'att_intervention_type_order_idx');
        });

        $this->create('attendance_cases', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('opened_from_alert_id')->nullable()->constrained('attendance_alerts')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reference_adult_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_adult_name')->nullable();
            $table->string('status', 40)->default('detected');
            $table->string('priority', 30)->default('preventive');
            $table->text('initial_situation')->nullable();
            $table->dateTime('opened_at');
            $table->dateTime('first_intervention_at')->nullable();
            $table->dateTime('last_intervention_at')->nullable();
            $table->date('next_review_on')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('closure_reason', 80)->nullable();
            $table->text('closure_notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'status', 'priority'], 'att_case_scope_idx');
            $table->index(['course_section_id', 'status'], 'att_case_course_idx');
            $table->index(['responsible_user_id', 'status', 'next_review_on'], 'att_case_owner_idx');
            $table->index(['student_profile_id', 'academic_year_id', 'status'], 'att_case_student_idx');
        });

        $this->create('attendance_case_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_case_id')->constrained('attendance_cases')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('participation_role', 80)->default('support');
            $table->boolean('active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['attendance_case_id', 'user_id'], 'att_case_participant_uq');
            $table->index(['user_id', 'active'], 'att_case_participant_user_idx');
        });

        $this->create('attendance_case_causes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_case_id')->constrained('attendance_cases')->restrictOnDelete();
            $table->foreignId('absence_reason_id')->constrained('attendance_absence_reasons')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('information_source', 80);
            $table->date('identified_on');
            $table->text('observations')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['attendance_case_id', 'absence_reason_id'], 'att_case_cause_uq');
            $table->index(['attendance_case_id', 'is_primary'], 'att_case_cause_primary_idx');
        });

        $this->create('attendance_case_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_case_id')->constrained('attendance_cases')->restrictOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at');

            $table->index(['attendance_case_id', 'changed_at'], 'att_case_history_idx');
        });

        $this->create('attendance_case_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_case_id')->constrained('attendance_cases')->restrictOnDelete();
            $table->text('note');
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attendance_case_id', 'created_at'], 'att_case_note_idx');
        });

        $this->create('attendance_family_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_case_id')->constrained('attendance_cases')->restrictOnDelete();
            $table->dateTime('contacted_at');
            $table->string('channel', 50);
            $table->string('contacted_person')->nullable();
            $table->string('result', 80);
            $table->text('observation')->nullable();
            $table->dateTime('next_contact_at')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attendance_case_id', 'contacted_at'], 'att_family_contact_case_idx');
            $table->index(['responsible_user_id', 'next_contact_at'], 'att_family_contact_due_idx');
        });

        if (! Schema::hasColumn('attendance_interventions', 'attendance_case_id')) {
            Schema::table('attendance_interventions', function (Blueprint $table) {
                $table->foreignId('attendance_case_id')->nullable()->after('id')->constrained('attendance_cases')->nullOnDelete();
                $table->foreignId('intervention_type_id')->nullable()->after('attendance_case_id')->constrained('attendance_intervention_types')->nullOnDelete();
                $table->text('result_summary')->nullable()->after('result');
                $table->dateTime('next_action_at')->nullable()->after('result_summary');

                $table->index(['attendance_case_id', 'opened_at'], 'att_intervention_case_date_idx');
                $table->index(['intervention_type_id', 'status'], 'att_intervention_type_status_idx');
            });
        }

        $this->create('attendance_action_plans', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->unique();
            $table->foreignId('attendance_case_id')->constrained('attendance_cases')->restrictOnDelete();
            $table->text('initial_situation');
            $table->decimal('initial_attendance_rate', 5, 2)->nullable();
            $table->unsignedSmallInteger('initial_lost_days')->default(0);
            $table->json('initial_patterns')->nullable();
            $table->json('identified_causes')->nullable();
            $table->text('objective');
            $table->string('goal_type', 60)->default('attendance_rate');
            $table->decimal('goal_value', 8, 2)->nullable();
            $table->unsignedSmallInteger('goal_window_days')->default(30);
            $table->date('starts_on');
            $table->date('review_on');
            $table->string('status', 30)->default('active');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('review_attendance_rate', 5, 2)->nullable();
            $table->decimal('result_variation', 6, 2)->nullable();
            $table->string('evaluation_result', 50)->nullable();
            $table->dateTime('evaluated_at')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attendance_case_id', 'status', 'review_on'], 'att_plan_case_status_idx');
            $table->index(['responsible_user_id', 'status', 'review_on'], 'att_plan_owner_due_idx');
        });

        $this->create('attendance_action_plan_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_action_plan_id')->constrained('attendance_action_plans')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('frequency', 50)->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attendance_action_plan_id', 'status', 'due_at'], 'att_plan_action_due_idx');
            $table->index(['responsible_user_id', 'status', 'due_at'], 'att_plan_action_owner_idx');
        });

        $this->create('attendance_meeting_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_case_id')->constrained('attendance_cases')->restrictOnDelete();
            $table->date('meeting_date');
            $table->text('agreement');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('commitment_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['meeting_date', 'status'], 'att_meeting_status_idx');
            $table->index(['responsible_user_id', 'commitment_date'], 'att_meeting_owner_idx');
        });
    }

    private function create(string $tableName, \Closure $definition): void
    {
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, $definition);
        }
    }

    public function down(): void
    {
        // Reversión deliberadamente no destructiva: el módulo conserva expedientes,
        // intervenciones y trazabilidad. Cualquier retiro requiere respaldo y un plan
        // de conservación de datos explícito fuera de una migración automática.
    }
};
