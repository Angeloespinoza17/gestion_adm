<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_statistics_audit_logs')) {
            // Resume a MySQL partial run stopped by the 64-character index-name limit.
            Schema::table('attendance_statistics_audit_logs', function (Blueprint $table) {
                $table->index(['auditable_type', 'auditable_id'], 'att_audit_auditable_idx');
                $table->index(['user_id', 'created_at'], 'att_audit_user_date_idx');
                $table->index(['action', 'created_at'], 'att_audit_action_date_idx');
            });

            return;
        }

        if (! Schema::hasTable('attendance_absence_reasons')) {
            Schema::create('attendance_absence_reasons', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 160);
                $table->string('category', 80)->nullable();
                $table->boolean('is_sensitive')->default(false);
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['active', 'sort_order'], 'att_abs_reason_active_order_idx');
            });

            Schema::table('attendance_records', function (Blueprint $table) {
                $table->foreignId('absence_reason_id')->nullable()->after('status')->constrained('attendance_absence_reasons')->nullOnDelete();
                $table->boolean('is_justified')->default(false)->after('absence_reason_id');
                $table->unsignedSmallInteger('minutes_late')->default(0)->after('is_justified');
                $table->boolean('early_departure')->default(false)->after('minutes_late');
                $table->time('arrival_time')->nullable()->after('early_departure');
                $table->time('departure_time')->nullable()->after('arrival_time');
                $table->timestamp('corrected_at')->nullable()->after('departure_time');
                $table->text('correction_reason')->nullable()->after('corrected_at');

                $table->index(['academic_year_id', 'is_justified', 'attendance_date'], 'att_record_justified_date_idx');
                $table->index(['academic_year_id', 'minutes_late', 'attendance_date'], 'att_record_late_date_idx');
                $table->index(['academic_year_id', 'early_departure', 'attendance_date'], 'att_record_departure_date_idx');
            });

            Schema::create('attendance_risk_levels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->cascadeOnDelete();
                $table->string('slug', 60);
                $table->string('name', 120);
                $table->decimal('minimum_rate', 5, 2)->default(0);
                $table->decimal('maximum_rate', 5, 2)->default(100);
                $table->string('color', 20)->default('#64748b');
                $table->string('icon', 80)->default('bx-shield');
                $table->unsignedSmallInteger('priority')->default(1);
                $table->text('suggested_actions')->nullable();
                $table->foreignId('default_responsible_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedSmallInteger('intervention_due_days')->nullable();
                $table->json('notification_channels')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['academic_year_id', 'slug'], 'att_risk_year_slug_unique');
                $table->index(['academic_year_id', 'active', 'priority'], 'att_risk_scope_idx');
            });

            Schema::create('attendance_alert_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->cascadeOnDelete();
                $table->string('code', 80);
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->string('metric', 80);
                $table->string('operator', 20)->default('lt');
                $table->decimal('threshold', 10, 2);
                $table->string('evaluation_period', 40)->default('academic_year');
                $table->string('severity', 20)->default('warning');
                $table->unsignedSmallInteger('cooldown_days')->default(7);
                $table->unsignedSmallInteger('response_due_days')->default(5);
                $table->boolean('auto_create_case')->default(false);
                $table->json('recipient_roles')->nullable();
                $table->json('notification_channels')->nullable();
                $table->json('scope')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['academic_year_id', 'code'], 'att_rule_year_code_unique');
                $table->index(['academic_year_id', 'active', 'severity'], 'att_rule_scope_idx');
            });

            Schema::create('attendance_goals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('scope_type', 40)->default('institution');
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->cascadeOnDelete();
                $table->date('starts_on');
                $table->date('ends_on');
                $table->decimal('target_rate', 5, 2);
                $table->string('status', 30)->default('active');
                $table->text('justification')->nullable();
                $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['academic_year_id', 'scope_type', 'scope_id', 'status'], 'att_goal_scope_idx');
                $table->index(['student_profile_id', 'status'], 'att_goal_student_idx');
            });

            Schema::create('attendance_interventions', function (Blueprint $table) {
                $table->id();
                $table->string('folio', 40)->unique();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('attendance_alert_id')->nullable()->constrained('attendance_alerts')->nullOnDelete();
                $table->foreignId('convivencia_case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
                $table->foreignId('risk_level_id')->nullable()->constrained('attendance_risk_levels')->nullOnDelete();
                $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 40)->default('new');
                $table->string('probable_cause', 120)->nullable();
                $table->text('description');
                $table->dateTime('opened_at');
                $table->dateTime('first_contact_at')->nullable();
                $table->dateTime('first_action_at')->nullable();
                $table->date('due_on')->nullable();
                $table->string('result', 80)->nullable();
                $table->dateTime('closed_at')->nullable();
                $table->text('closure_reason')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['academic_year_id', 'status', 'due_on'], 'att_intervention_status_due_idx');
                $table->index(['student_profile_id', 'status'], 'att_intervention_student_idx');
                $table->index(['responsible_user_id', 'status'], 'att_intervention_owner_idx');
            });

            Schema::create('attendance_intervention_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_intervention_id');
                $table->string('action_type', 80);
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->dateTime('scheduled_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->string('status', 30)->default('pending');
                $table->foreignId('responsible_user_id')->nullable();
                $table->json('evidence')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->foreignId('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('attendance_intervention_id', 'att_action_intervention_fk')->references('id')->on('attendance_interventions')->cascadeOnDelete();
                $table->foreign('responsible_user_id', 'att_action_responsible_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by', 'att_action_created_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'att_action_updated_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['attendance_intervention_id', 'status', 'scheduled_at'], 'att_action_status_date_idx');
            });
        } elseif (! Schema::hasTable('attendance_saved_filters')) {
            // MySQL may leave this table after rejecting Laravel's long automatic FK name.
            Schema::table('attendance_intervention_actions', function (Blueprint $table) {
                $table->foreign('attendance_intervention_id', 'att_action_intervention_fk')->references('id')->on('attendance_interventions')->cascadeOnDelete();
                $table->foreign('responsible_user_id', 'att_action_responsible_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by', 'att_action_created_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'att_action_updated_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['attendance_intervention_id', 'status', 'scheduled_at'], 'att_action_status_date_idx');
            });
        }

        Schema::create('attendance_saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('filters');
            $table->boolean('is_institutional')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'att_saved_filter_user_name_unique');
        });

        Schema::create('attendance_dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('layout')->nullable();
            $table->json('visible_widgets')->nullable();
            $table->json('favorite_kpis')->nullable();
            $table->string('default_view', 40)->default('dashboard');
            $table->timestamps();
        });

        Schema::create('attendance_financial_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('subsidy_type', 80)->default('general');
            $table->decimal('unit_value', 16, 4);
            $table->decimal('attendance_factor', 10, 6)->default(1);
            $table->char('currency', 3)->default('CLP');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('source_reference')->nullable();
            $table->text('assumptions')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_year_id', 'active', 'valid_from'], 'att_financial_year_valid_idx');
        });

        Schema::create('attendance_projection_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->string('method', 60);
            $table->string('model_version', 40)->default('1.0');
            $table->json('inputs');
            $table->json('results');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'course_section_id', 'created_at'], 'att_projection_scope_idx');
        });

        Schema::create('attendance_scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('report_type', 80);
            $table->string('format', 10)->default('pdf');
            $table->string('frequency', 30);
            $table->time('run_at')->default('07:00');
            $table->json('filters')->nullable();
            $table->json('recipients')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'next_run_at'], 'att_scheduled_due_idx');
        });

        Schema::create('attendance_export_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('report_type', 80);
            $table->string('format', 10);
            $table->string('status', 30)->default('pending');
            $table->json('filters')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedSmallInteger('progress')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'att_export_user_status_idx');
        });

        Schema::create('attendance_data_quality_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->string('type', 80);
            $table->string('severity', 20)->default('warning');
            $table->string('status', 30)->default('open');
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->text('suggested_action')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'status', 'severity'], 'att_quality_status_idx');
            $table->index(['course_section_id', 'status'], 'att_quality_course_idx');
        });

        Schema::create('attendance_statistics_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('action', 100);
            $table->string('origin', 60)->default('web');
            $table->ipAddress('ip_address')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changes')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['auditable_type', 'auditable_id'], 'att_audit_auditable_idx');
            $table->index(['user_id', 'created_at'], 'att_audit_user_date_idx');
            $table->index(['action', 'created_at'], 'att_audit_action_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_statistics_audit_logs');
        Schema::dropIfExists('attendance_data_quality_issues');
        Schema::dropIfExists('attendance_export_jobs');
        Schema::dropIfExists('attendance_scheduled_reports');
        Schema::dropIfExists('attendance_projection_runs');
        Schema::dropIfExists('attendance_financial_parameters');
        Schema::dropIfExists('attendance_dashboard_preferences');
        Schema::dropIfExists('attendance_saved_filters');
        Schema::dropIfExists('attendance_intervention_actions');
        Schema::dropIfExists('attendance_interventions');
        Schema::dropIfExists('attendance_goals');
        Schema::dropIfExists('attendance_alert_rules');
        Schema::dropIfExists('attendance_risk_levels');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['absence_reason_id']);
            $table->dropIndex('att_record_justified_date_idx');
            $table->dropIndex('att_record_late_date_idx');
            $table->dropIndex('att_record_departure_date_idx');
            $table->dropColumn([
                'absence_reason_id', 'is_justified', 'minutes_late', 'early_departure',
                'arrival_time', 'departure_time', 'corrected_at', 'correction_reason',
            ]);
        });

        Schema::dropIfExists('attendance_absence_reasons');
    }
};
