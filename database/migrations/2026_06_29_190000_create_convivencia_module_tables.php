<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convivencia_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->string('group', 80);
            $table->string('code', 80);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('color', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group', 'code'], 'convivencia_catalog_group_code_unique');
            $table->index(['group', 'active'], 'convivencia_catalog_group_active_idx');
        });

        Schema::create('convivencia_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('label', 160);
            $table->json('value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('convivencia_external_institutions', function (Blueprint $table) {
            $table->id();
            $table->string('category', 120)->nullable();
            $table->string('name', 191);
            $table->string('contact_name', 160)->nullable();
            $table->string('contact_email', 191)->nullable();
            $table->string('contact_phone', 80)->nullable();
            $table->string('address', 191)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'active'], 'convivencia_external_inst_cat_active_idx');
        });

        Schema::create('convivencia_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('name', 191);
            $table->longText('general_objective');
            $table->json('specific_objectives')->nullable();
            $table->longText('resources_required')->nullable();
            $table->longText('indicators_summary')->nullable();
            $table->longText('verification_means_summary')->nullable();
            $table->string('status', 50)->default('borrador');
            $table->decimal('advance_percentage', 5, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->longText('observations')->nullable();
            $table->longText('final_evaluation')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_year_id', 'status'], 'convivencia_plans_year_status_idx');
            $table->index(['responsible_user_id', 'status'], 'convivencia_plans_responsible_status_idx');
        });

        Schema::create('convivencia_plan_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('convivencia_plans')->cascadeOnDelete();
            $table->foreignId('dimension_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('responsible_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('action_type', 50)->default('preventiva');
            $table->string('title', 191);
            $table->longText('description')->nullable();
            $table->string('dimension_label', 160)->nullable();
            $table->string('responsible_label', 160)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->longText('required_resources')->nullable();
            $table->longText('indicator_summary')->nullable();
            $table->longText('verification_means')->nullable();
            $table->string('status', 50)->default('borrador');
            $table->decimal('advance_percentage', 5, 2)->default(0);
            $table->longText('observations')->nullable();
            $table->longText('evidence_summary')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'status'], 'convivencia_plan_actions_plan_status_idx');
            $table->index(['action_type', 'status'], 'convivencia_plan_actions_type_status_idx');
        });

        Schema::create('convivencia_cases', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->unique();
            $table->nullableMorphs('sourceable');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('case_type_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('classification_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('subclassification_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('criticality_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('happened_at')->nullable();
            $table->string('origin', 80);
            $table->string('status', 50)->default('abierto');
            $table->string('case_type_label', 160)->nullable();
            $table->string('classification_label', 160)->nullable();
            $table->string('subclassification_label', 160)->nullable();
            $table->string('criticality_label', 100)->nullable();
            $table->string('place', 160)->nullable();
            $table->longText('initial_report');
            $table->longText('background')->nullable();
            $table->longText('immediate_measures')->nullable();
            $table->longText('safeguarding_measures')->nullable();
            $table->longText('internal_notes')->nullable();
            $table->longText('resolution')->nullable();
            $table->longText('conclusion')->nullable();
            $table->dateTime('follow_up_due_at')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'opened_at'], 'convivencia_cases_status_opened_idx');
            $table->index(['course_section_id', 'status'], 'convivencia_cases_course_status_idx');
            $table->index(['criticality_label', 'status'], 'convivencia_cases_criticality_status_idx');
            $table->index(['responsible_user_id', 'status'], 'convivencia_cases_responsible_status_idx');
        });

        Schema::create('convivencia_case_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('convivencia_cases')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->string('person_type', 60);
            $table->string('role_type', 60);
            $table->string('full_name', 191);
            $table->string('identifier', 80)->nullable();
            $table->string('relationship_label', 120)->nullable();
            $table->string('contact_reference', 191)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();

            $table->index(['case_id', 'role_type'], 'convivencia_case_people_case_role_idx');
            $table->index(['student_profile_id', 'person_type'], 'convivencia_case_people_student_type_idx');
        });

        Schema::create('convivencia_case_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('convivencia_cases')->cascadeOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('follow_up_at');
            $table->string('entry_type', 60)->default('seguimiento');
            $table->string('status', 50)->default('registrado');
            $table->string('title', 191)->nullable();
            $table->longText('notes');
            $table->dateTime('next_follow_up_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'follow_up_at'], 'convivencia_case_followups_case_date_idx');
            $table->index(['status', 'next_follow_up_at'], 'convivencia_case_followups_status_next_idx');
        });

        Schema::create('convivencia_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_type_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('criticality_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->string('name', 191);
            $table->string('type_label', 160)->nullable();
            $table->string('criticality_label', 100)->nullable();
            $table->longText('description')->nullable();
            $table->longText('required_documents')->nullable();
            $table->longText('safeguard_measures')->nullable();
            $table->longText('minimal_actions')->nullable();
            $table->unsignedSmallInteger('default_due_days')->nullable();
            $table->string('status', 50)->default('activo');
            $table->boolean('is_sensitive')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'criticality_label'], 'convivencia_protocols_status_criticality_idx');
        });

        Schema::create('convivencia_protocol_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained('convivencia_protocols')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order')->default(1);
            $table->string('stage_name', 160);
            $table->string('responsible_label', 160)->nullable();
            $table->unsignedSmallInteger('due_days')->nullable();
            $table->longText('required_documents')->nullable();
            $table->longText('minimal_actions')->nullable();
            $table->longText('safeguard_measures')->nullable();
            $table->timestamps();

            $table->unique(['protocol_id', 'step_order'], 'convivencia_protocol_steps_protocol_order_unique');
        });

        Schema::create('convivencia_complaints', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->unique();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('affected_student_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('situation_type_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
            $table->string('complainant_name', 191)->nullable();
            $table->string('complainant_type', 60);
            $table->string('contact_email', 191)->nullable();
            $table->string('contact_phone', 80)->nullable();
            $table->string('situation_type_label', 160)->nullable();
            $table->string('place', 160)->nullable();
            $table->dateTime('received_at');
            $table->dateTime('happened_at')->nullable();
            $table->longText('report_text');
            $table->json('involved_snapshot')->nullable();
            $table->boolean('truth_declaration_accepted')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_sensitive')->default(true);
            $table->string('status', 50)->default('recibida');
            $table->longText('admissibility_result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'received_at'], 'convivencia_complaints_status_received_idx');
            $table->index(['affected_student_id', 'status'], 'convivencia_complaints_student_status_idx');
            $table->index(['course_section_id', 'status'], 'convivencia_complaints_course_status_idx');
        });

        Schema::create('convivencia_protocol_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained('convivencia_protocols')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
            $table->foreignId('complaint_id')->nullable()->constrained('convivencia_complaints')->nullOnDelete();
            $table->foreignId('current_step_id')->nullable()->constrained('convivencia_protocol_steps')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('activated_at');
            $table->string('status', 50)->default('activo');
            $table->string('current_stage_name', 160)->nullable();
            $table->dateTime('due_at')->nullable();
            $table->json('involved_snapshot')->nullable();
            $table->longText('actions_taken')->nullable();
            $table->longText('measures_adopted')->nullable();
            $table->longText('closing_summary')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_at'], 'convivencia_protocol_activations_status_due_idx');
            $table->index(['case_id', 'status'], 'convivencia_protocol_activations_case_status_idx');
            $table->index(['complaint_id', 'status'], 'convivencia_protocol_activations_complaint_status_idx');
        });

        Schema::create('convivencia_protocol_activation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activation_id')->constrained('convivencia_protocol_activations')->cascadeOnDelete();
            $table->foreignId('protocol_step_id')->nullable()->constrained('convivencia_protocol_steps')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 80)->default('avance');
            $table->string('stage_name', 160)->nullable();
            $table->longText('notes')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['activation_id', 'created_at'], 'convivencia_protocol_logs_activation_date_idx');
        });

        Schema::create('convivencia_derivations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('destination_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('destination_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('destination_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('external_institution_id')->nullable()->constrained('convivencia_external_institutions')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 30)->default('internal');
            $table->string('status', 50)->default('ingresada');
            $table->string('priority_level', 50)->default('media');
            $table->string('confidentiality_level', 50)->default('reservada');
            $table->string('destination_label', 191)->nullable();
            $table->string('external_contact_name', 160)->nullable();
            $table->string('external_contact_email', 191)->nullable();
            $table->string('external_contact_phone', 80)->nullable();
            $table->dateTime('derived_at');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('response_due_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->text('motive');
            $table->longText('narrative')->nullable();
            $table->longText('response_text')->nullable();
            $table->longText('suggested_actions')->nullable();
            $table->longText('follow_up_notes')->nullable();
            $table->boolean('is_sensitive')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scope', 'status'], 'convivencia_derivations_scope_status_idx');
            $table->index(['student_profile_id', 'status'], 'convivencia_derivations_student_status_idx');
            $table->index(['response_due_at', 'status'], 'convivencia_derivations_due_status_idx');
        });

        Schema::create('convivencia_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('measure_type_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('measure_type_label', 160)->nullable();
            $table->longText('description');
            $table->longText('training_objective');
            $table->dateTime('assigned_at');
            $table->dateTime('due_at')->nullable();
            $table->string('status', 50)->default('asignada');
            $table->longText('evidence_summary')->nullable();
            $table->longText('student_reflection')->nullable();
            $table->longText('repair_action')->nullable();
            $table->longText('responsible_notes')->nullable();
            $table->longText('closure_notes')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->boolean('is_sensitive')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_at'], 'convivencia_measures_status_due_idx');
            $table->index(['student_profile_id', 'status'], 'convivencia_measures_student_status_idx');
        });

        Schema::create('convivencia_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('interview_type_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('interview_type_label', 160)->nullable();
            $table->dateTime('interview_at');
            $table->text('motive');
            $table->longText('topics')->nullable();
            $table->longText('agreements')->nullable();
            $table->longText('commitments')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('follow_up_status', 50)->default('pendiente');
            $table->longText('internal_notes')->nullable();
            $table->boolean('is_sensitive')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['interview_at', 'follow_up_status'], 'convivencia_interviews_date_status_idx');
            $table->index(['case_id', 'interview_at'], 'convivencia_interviews_case_date_idx');
        });

        Schema::create('convivencia_interview_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('convivencia_interviews')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('participant_type', 60);
            $table->string('participant_role', 80)->nullable();
            $table->string('full_name', 191);
            $table->string('contact_reference', 191)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['interview_id', 'participant_type'], 'convivencia_interview_participants_type_idx');
        });

        Schema::create('convivencia_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
            $table->foreignId('generated_derivation_id')->nullable()->constrained('convivencia_derivations')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('daily_log_type_item_id')->nullable()->constrained('convivencia_catalog_items')->nullOnDelete();
            $table->foreignId('inspector_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('inspector_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->dateTime('happened_at');
            $table->string('daily_log_type_label', 160)->nullable();
            $table->string('place', 160)->nullable();
            $table->longText('description');
            $table->longText('immediate_action')->nullable();
            $table->json('involved_snapshot')->nullable();
            $table->boolean('guardian_informed')->default(false);
            $table->text('guardian_contact_note')->nullable();
            $table->string('status', 50)->default('registrado');
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['happened_at', 'status'], 'convivencia_daily_logs_date_status_idx');
            $table->index(['course_section_id', 'status'], 'convivencia_daily_logs_course_status_idx');
        });

        Schema::create('convivencia_sociograms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->string('title', 191);
            $table->date('applied_on');
            $table->string('status', 50)->default('borrador');
            $table->string('confidentiality_level', 50)->default('alta_confidencialidad');
            $table->json('matrix_summary')->nullable();
            $table->json('result_summary')->nullable();
            $table->longText('interpretation')->nullable();
            $table->boolean('is_sensitive')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_section_id', 'applied_on'], 'convivencia_sociograms_course_date_idx');
        });

        Schema::create('convivencia_sociogram_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sociogram_id')->constrained('convivencia_sociograms')->cascadeOnDelete();
            $table->string('prompt', 191);
            $table->string('selection_type', 30)->default('positiva');
            $table->unsignedTinyInteger('max_choices')->default(3);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('convivencia_sociogram_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sociogram_id')->constrained('convivencia_sociograms')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('convivencia_sociogram_questions')->cascadeOnDelete();
            $table->foreignId('respondent_student_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('selected_student_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->string('selection_type', 30)->default('positiva');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sociogram_id', 'selection_type'], 'convivencia_sociogram_answers_type_idx');
        });

        Schema::create('convivencia_idps_dimensions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('convivencia_idps_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('name', 160);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 50)->default('abierto');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'status'], 'convivencia_idps_periods_year_status_idx');
        });

        Schema::create('convivencia_idps_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_id')->nullable()->constrained('convivencia_idps_dimensions')->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('response_type', 80)->default('escala');
            $table->string('scale_label', 160)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('convivencia_idps_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('convivencia_idps_periods')->cascadeOnDelete();
            $table->foreignId('dimension_id')->constrained('convivencia_idps_dimensions')->cascadeOnDelete();
            $table->foreignId('instrument_id')->nullable()->constrained('convivencia_idps_instruments')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained('education_levels')->nullOnDelete();
            $table->foreignId('related_plan_id')->nullable()->constrained('convivencia_plans')->nullOnDelete();
            $table->string('result_scope', 40)->default('curso');
            $table->string('reference_label', 191)->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedInteger('sample_size')->nullable();
            $table->longText('qualitative_observations')->nullable();
            $table->longText('improvement_actions')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_id', 'result_scope'], 'convivencia_idps_results_period_scope_idx');
        });

        Schema::create('convivencia_attachments', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('attachable');
            $table->foreignId('case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->string('category', 80)->default('otro');
            $table->string('confidentiality_level', 50)->default('general');
            $table->boolean('is_sensitive')->default(false);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['case_id', 'category'], 'convivencia_attachments_case_category_idx');
        });

        Schema::create('convivencia_status_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('loggable');
            $table->foreignId('case_id')->nullable()->constrained('convivencia_cases')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status', 80)->nullable();
            $table->string('new_status', 80);
            $table->string('event_type', 80)->default('status_change');
            $table->text('comment')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->index(['case_id', 'changed_at'], 'convivencia_status_logs_case_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convivencia_status_logs');
        Schema::dropIfExists('convivencia_attachments');
        Schema::dropIfExists('convivencia_idps_results');
        Schema::dropIfExists('convivencia_idps_instruments');
        Schema::dropIfExists('convivencia_idps_periods');
        Schema::dropIfExists('convivencia_idps_dimensions');
        Schema::dropIfExists('convivencia_sociogram_answers');
        Schema::dropIfExists('convivencia_sociogram_questions');
        Schema::dropIfExists('convivencia_sociograms');
        Schema::dropIfExists('convivencia_daily_logs');
        Schema::dropIfExists('convivencia_interview_participants');
        Schema::dropIfExists('convivencia_interviews');
        Schema::dropIfExists('convivencia_measures');
        Schema::dropIfExists('convivencia_derivations');
        Schema::dropIfExists('convivencia_protocol_activation_logs');
        Schema::dropIfExists('convivencia_protocol_activations');
        Schema::dropIfExists('convivencia_complaints');
        Schema::dropIfExists('convivencia_protocol_steps');
        Schema::dropIfExists('convivencia_protocols');
        Schema::dropIfExists('convivencia_case_followups');
        Schema::dropIfExists('convivencia_case_people');
        Schema::dropIfExists('convivencia_cases');
        Schema::dropIfExists('convivencia_plan_actions');
        Schema::dropIfExists('convivencia_plans');
        Schema::dropIfExists('convivencia_external_institutions');
        Schema::dropIfExists('convivencia_settings');
        Schema::dropIfExists('convivencia_catalog_items');
    }
};
