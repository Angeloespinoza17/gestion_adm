<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_work_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique('year');
        });

        Schema::create('social_work_cases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('primary_student_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('origin', 80)->nullable()->index();
            $table->string('case_type', 80)->nullable()->index();
            $table->string('reason')->nullable();
            $table->longText('initial_description')->nullable();
            $table->date('received_on')->nullable();
            $table->date('opened_on')->index();
            $table->string('priority', 20)->default('media')->index();
            $table->string('risk_level', 20)->default('sin_evaluar')->index();
            $table->string('confidentiality', 30)->default('restringido')->index();
            $table->string('status', 40)->default('borrador')->index();
            $table->text('initial_protocol')->nullable();
            $table->text('initial_safeguards')->nullable();
            $table->string('next_milestone')->nullable();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('last_activity_at')->nullable()->index();
            $table->longText('closure_conclusion')->nullable();
            $table->string('closure_result')->nullable();
            $table->string('closure_reason')->nullable();
            $table->string('final_risk_level', 20)->nullable();
            $table->text('post_closure_follow_up')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('reopen_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['primary_student_id', 'status'], 'sw_case_student_status_idx');
            $table->index(['responsible_user_id', 'status'], 'sw_case_responsible_status_idx');
            $table->index(['academic_year_id', 'status'], 'sw_case_year_status_idx');
        });

        Schema::create('social_work_case_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('social_work_cases')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->string('relationship', 50)->default('relacionado');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['case_id', 'student_profile_id']);
        });

        Schema::create('social_work_case_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('social_work_cases')->cascadeOnDelete();
            $table->string('name');
            $table->string('identifier', 40)->nullable();
            $table->string('relationship', 80)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('role', 80)->nullable();
            $table->string('confidentiality', 30)->default('restringido');
            $table->timestamps();
        });

        Schema::create('social_work_case_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('social_work_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 40)->default('colaborador');
            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'ended_at']);
        });

        Schema::create('social_work_case_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('social_work_cases')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->dateTime('changed_at')->index();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['case_id', 'changed_at']);
        });

        Schema::create('social_work_case_reopenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('social_work_cases')->cascadeOnDelete();
            $table->dateTime('reopened_at');
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('reason');
            $table->longText('previous_conclusion')->nullable();
            $table->string('risk_level', 20);
            $table->string('priority', 20);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('next_action');
            $table->timestamps();
        });

        Schema::create('social_work_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->string('kind', 40)->index();
            $table->string('type', 80)->nullable()->index();
            $table->date('activity_date')->index();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('modality', 40)->nullable();
            $table->string('place')->nullable();
            $table->string('contact_number', 60)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_relationship', 80)->nullable();
            $table->string('contact_result', 60)->nullable();
            $table->string('objective')->nullable();
            $table->longText('description')->nullable();
            $table->longText('professional_observations')->nullable();
            $table->longText('highly_confidential_notes')->nullable();
            $table->text('result')->nullable();
            $table->text('agreements')->nullable();
            $table->text('next_action')->nullable();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('next_intervention_at')->nullable();
            $table->string('status', 30)->default('borrador')->index();
            $table->string('confidentiality', 30)->default('restringido')->index();
            $table->json('participants')->nullable();
            $table->json('structured_data')->nullable();
            $table->foreignId('template_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['case_id', 'activity_date']);
            $table->index(['student_profile_id', 'activity_date']);
        });

        Schema::create('social_work_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->cascadeOnDelete();
            $table->foreignId('intervention_id')->nullable()->constrained('social_work_interventions')->cascadeOnDelete();
            $table->string('description');
            $table->string('responsible_name')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable()->index();
            $table->string('status', 30)->default('pendiente')->index();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('social_work_protocols', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->date('effective_from')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('social_work_protocol_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained('social_work_protocols')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 30)->default('borrador');
            $table->date('effective_from')->nullable();
            $table->json('steps');
            $table->json('required_documents')->nullable();
            $table->json('safeguards')->nullable();
            $table->json('fields')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['protocol_id', 'version']);
        });

        Schema::create('social_work_case_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('social_work_cases')->cascadeOnDelete();
            $table->foreignId('protocol_id')->constrained('social_work_protocols')->restrictOnDelete();
            $table->foreignId('protocol_version_id')->constrained('social_work_protocol_versions')->restrictOnDelete();
            $table->dateTime('activated_at')->index();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->unsignedInteger('current_step')->default(0);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable()->index();
            $table->string('status', 30)->default('activo')->index();
            $table->text('conclusion')->nullable();
            $table->json('version_snapshot');
            $table->timestamps();
        });

        Schema::create('social_work_case_protocol_step_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_protocol_id')->constrained('social_work_case_protocols')->cascadeOnDelete();
            $table->unsignedInteger('step_index');
            $table->morphs('linkable', 'sw_protocol_step_linkable_idx');
            $table->string('link_type', 40)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['case_protocol_id', 'step_index', 'linkable_type', 'linkable_id'], 'sw_protocol_step_link_unique');
        });

        Schema::create('social_work_protocol_zero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('social_work_cases')->cascadeOnDelete();
            $table->dateTime('received_at');
            $table->string('channel', 60);
            $table->string('informant_name');
            $table->string('informant_relationship', 80)->nullable();
            $table->longText('initial_account');
            $table->string('immediate_risk', 30)->default('no_observado');
            $table->boolean('urgent_attention')->default(false);
            $table->text('initial_safeguards')->nullable();
            $table->json('notified_people')->nullable();
            $table->foreignId('evaluation_responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('evaluation_due_at')->index();
            $table->text('pending_background')->nullable();
            $table->string('decision', 50)->nullable();
            $table->text('decision_basis')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->string('status', 30)->default('recibido');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('social_work_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->date('referral_date')->index();
            $table->string('source_unit', 80);
            $table->string('source_person')->nullable();
            $table->string('reason');
            $table->longText('description')->nullable();
            $table->text('observed_background')->nullable();
            $table->text('previous_actions')->nullable();
            $table->string('urgency', 20)->default('normal')->index();
            $table->boolean('immediate_risk')->default(false);
            $table->string('contact_data')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->string('status', 40)->default('borrador')->index();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('confidentiality', 30)->default('restringido');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_user_id', 'status']);
        });

        Schema::create('social_work_pedagogical_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->nullOnDelete();
            $table->foreignId('requested_from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('period', 80)->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('responded_at')->nullable();
            $table->string('status', 40)->default('solicitado')->index();
            $table->json('response_data')->nullable();
            $table->text('correction_request')->nullable();
            $table->timestamps();
        });

        Schema::create('social_work_requested_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('social_work_cases')->cascadeOnDelete();
            $table->string('item');
            $table->string('requested_from')->nullable();
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('due_at')->nullable()->index();
            $table->string('status', 30)->default('pendiente')->index();
            $table->text('notes')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_work_requested_information');
        Schema::dropIfExists('social_work_pedagogical_reports');
        Schema::dropIfExists('social_work_referrals');
        Schema::dropIfExists('social_work_protocol_zero');
        Schema::dropIfExists('social_work_case_protocol_step_links');
        Schema::dropIfExists('social_work_case_protocols');
        Schema::dropIfExists('social_work_protocol_versions');
        Schema::dropIfExists('social_work_protocols');
        Schema::dropIfExists('social_work_commitments');
        Schema::dropIfExists('social_work_interventions');
        Schema::dropIfExists('social_work_case_reopenings');
        Schema::dropIfExists('social_work_case_status_history');
        Schema::dropIfExists('social_work_case_assignments');
        Schema::dropIfExists('social_work_case_people');
        Schema::dropIfExists('social_work_case_students');
        Schema::dropIfExists('social_work_cases');
        Schema::dropIfExists('social_work_sequences');
    }
};
