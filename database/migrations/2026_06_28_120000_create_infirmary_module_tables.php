<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infirmary_medications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('commercial_name')->nullable();
            $table->string('active_ingredient')->nullable();
            $table->string('presentation', 120)->nullable();
            $table->string('concentration', 120)->nullable();
            $table->string('unit', 40)->nullable();
            $table->string('laboratory', 160)->nullable();
            $table->decimal('current_stock', 10, 2)->default(0);
            $table->decimal('minimum_stock', 10, 2)->default(0);
            $table->string('physical_location', 160)->nullable();
            $table->string('batch', 120)->nullable();
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->string('status', 40)->default('disponible');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at'], 'inf_med_status_exp_idx');
            $table->index(['current_stock', 'minimum_stock'], 'inf_med_stock_min_idx');
        });

        Schema::create('infirmary_medication_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained('infirmary_medications')->cascadeOnDelete();
            $table->string('movement_type', 40);
            $table->decimal('quantity', 10, 2);
            $table->decimal('stock_before', 10, 2)->default(0);
            $table->decimal('stock_after', 10, 2)->default(0);
            $table->string('reason', 191)->nullable();
            $table->text('notes')->nullable();
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->dateTime('moved_at');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['movement_type', 'moved_at'], 'inf_med_mov_type_date_idx');
            $table->index(['reference_type', 'reference_id'], 'inf_med_mov_ref_idx');
        });

        Schema::create('infirmary_medication_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id');
            $table->foreign('student_profile_id', 'inf_med_auth_student_fk')->references('id')->on('student_profiles')->cascadeOnDelete();
            $table->foreignId('medication_id');
            $table->foreign('medication_id', 'inf_med_auth_med_fk')->references('id')->on('infirmary_medications')->cascadeOnDelete();
            $table->text('diagnosis')->nullable();
            $table->string('dose', 120);
            $table->string('frequency', 120)->nullable();
            $table->string('schedule_text', 191)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('physician_name', 160)->nullable();
            $table->date('medical_authorization_expires_at')->nullable();
            $table->date('guardian_authorization_expires_at')->nullable();
            $table->text('observations')->nullable();
            $table->string('status', 40)->default('vigente');
            $table->foreignId('created_by')->nullable();
            $table->foreign('created_by', 'inf_med_auth_created_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable();
            $table->foreign('updated_by', 'inf_med_auth_updated_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_profile_id', 'status'], 'inf_med_auth_student_status_idx');
            $table->index(['start_date', 'end_date'], 'inf_med_auth_dates_idx');
        });

        Schema::create('infirmary_attentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('teacher_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('referred_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('dependency_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->foreignId('attended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attention_category', 120);
            $table->dateTime('attended_at');
            $table->string('student_full_name_snapshot', 160);
            $table->string('student_rut_snapshot', 20)->nullable();
            $table->string('course_name_snapshot', 160)->nullable();
            $table->string('teacher_name_snapshot', 160)->nullable();
            $table->unsignedSmallInteger('age_snapshot')->nullable();
            $table->string('accompanied_by_type', 40)->default('sin_acompanante');
            $table->string('accompanied_by_name', 160)->nullable();
            $table->text('consultation_reason');
            $table->text('initial_description')->nullable();
            $table->text('observations')->nullable();
            $table->unsignedSmallInteger('attention_duration_minutes')->nullable();
            $table->string('priority', 40)->default('media');
            $table->string('status', 40)->default('abierta');
            $table->dateTime('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_profile_id', 'attended_at'], 'inf_attn_student_date_idx');
            $table->index(['status', 'priority'], 'inf_attn_status_priority_idx');
            $table->index(['attention_category', 'attended_at'], 'inf_attn_category_date_idx');
        });

        Schema::create('infirmary_attention_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attention_id')->constrained('infirmary_attentions')->cascadeOnDelete();
            $table->json('treatment_types')->nullable();
            $table->string('treatment_other', 160)->nullable();
            $table->foreignId('medication_id')->nullable()->constrained('infirmary_medications')->nullOnDelete();
            $table->decimal('medication_quantity', 10, 2)->nullable();
            $table->string('blood_pressure', 40)->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('oxygen_saturation')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->text('vital_signs_notes')->nullable();
            $table->boolean('emotional_support_required')->default(false);
            $table->text('emotional_comment')->nullable();
            $table->string('emotional_support_type', 160)->nullable();
            $table->unsignedSmallInteger('emotional_duration_minutes')->nullable();
            $table->foreignId('emotional_professional_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->text('other_treatments')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('infirmary_attention_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attention_id')->constrained('infirmary_attentions')->cascadeOnDelete();
            $table->string('referral_type', 120);
            $table->dateTime('referred_at');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsible_name', 160)->nullable();
            $table->text('reason')->nullable();
            $table->text('observations')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();

            $table->index(['attention_id', 'referred_at'], 'inf_ref_attention_date_idx');
        });

        Schema::create('infirmary_attention_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('attention_id')->nullable()->constrained('infirmary_attentions')->cascadeOnDelete();
            $table->dateTime('called_at');
            $table->string('person_contacted', 160);
            $table->string('relationship', 120)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('call_status', 40)->default('pendiente');
            $table->string('reason', 191)->nullable();
            $table->text('conversation_summary')->nullable();
            $table->text('commitments')->nullable();
            $table->dateTime('estimated_arrival_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->foreignId('called_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_profile_id', 'called_at'], 'inf_call_student_date_idx');
            $table->index(['call_status', 'called_at'], 'inf_call_status_date_idx');
        });

        Schema::create('infirmary_attention_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attention_id')->constrained('infirmary_attentions')->cascadeOnDelete();
            $table->dateTime('followed_at');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment');
            $table->string('status', 40)->default('pendiente');
            $table->dateTime('next_review_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['attention_id', 'followed_at'], 'inf_follow_attention_date_idx');
            $table->index(['status', 'next_review_at'], 'inf_follow_status_next_idx');
        });

        Schema::create('infirmary_medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorization_id')->nullable();
            $table->foreign('authorization_id', 'inf_med_admin_auth_fk')->references('id')->on('infirmary_medication_authorizations')->nullOnDelete();
            $table->foreignId('attention_id')->nullable();
            $table->foreign('attention_id', 'inf_med_admin_attention_fk')->references('id')->on('infirmary_attentions')->nullOnDelete();
            $table->foreignId('medication_id');
            $table->foreign('medication_id', 'inf_med_admin_med_fk')->references('id')->on('infirmary_medications')->cascadeOnDelete();
            $table->foreignId('student_profile_id');
            $table->foreign('student_profile_id', 'inf_med_admin_student_fk')->references('id')->on('student_profiles')->cascadeOnDelete();
            $table->dateTime('administered_at');
            $table->foreignId('administered_by_user_id')->nullable();
            $table->foreign('administered_by_user_id', 'inf_med_admin_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->decimal('quantity_administered', 10, 2);
            $table->string('schedule_reference', 120)->nullable();
            $table->string('source_type', 40)->default('atencion');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['student_profile_id', 'administered_at'], 'inf_admin_student_date_idx');
            $table->index(['authorization_id', 'administered_at'], 'inf_admin_auth_date_idx');
        });

        Schema::create('infirmary_accidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attention_id')->nullable()->constrained('infirmary_attentions')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('dependency_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->string('accident_type', 120);
            $table->string('place', 160)->nullable();
            $table->string('activity', 160)->nullable();
            $table->text('description');
            $table->text('witnesses')->nullable();
            $table->foreignId('present_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('severity', 40)->default('leve');
            $table->text('observed_injuries')->nullable();
            $table->text('first_aid')->nullable();
            $table->string('guardian_call_status', 40)->nullable();
            $table->string('referral_destination', 160)->nullable();
            $table->boolean('school_insurance')->default(false);
            $table->string('diat_number', 80)->nullable();
            $table->dateTime('diat_generated_at')->nullable();
            $table->text('observations')->nullable();
            $table->string('case_status', 40)->default('abierto');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['occurred_at', 'case_status'], 'inf_acc_case_date_idx');
            $table->index(['student_profile_id', 'severity'], 'inf_acc_student_sev_idx');
        });

        Schema::create('infirmary_documents', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('documentable');
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->string('category', 80)->default('otro');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_profile_id', 'category'], 'inf_docs_student_cat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infirmary_documents');
        Schema::dropIfExists('infirmary_accidents');
        Schema::dropIfExists('infirmary_medication_administrations');
        Schema::dropIfExists('infirmary_attention_follow_ups');
        Schema::dropIfExists('infirmary_attention_calls');
        Schema::dropIfExists('infirmary_attention_referrals');
        Schema::dropIfExists('infirmary_attention_treatments');
        Schema::dropIfExists('infirmary_attentions');
        Schema::dropIfExists('infirmary_medication_authorizations');
        Schema::dropIfExists('infirmary_medication_movements');
        Schema::dropIfExists('infirmary_medications');
    }
};
