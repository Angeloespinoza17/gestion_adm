<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apoyo_profesionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('area_slug', 80);
            $table->string('area_name', 120);
            $table->string('professional_role_slug', 80)->nullable();
            $table->string('professional_role_name', 120);
            $table->boolean('can_receive_derivations')->default(true);
            $table->boolean('can_manage_confidential_cases')->default(false);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'area_slug'], 'apoyo_profesionales_user_area_unique');
            $table->index(['area_slug', 'active'], 'apoyo_profesionales_area_active_idx');
        });

        Schema::create('apoyo_config_tipos_atencion', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->boolean('requires_other_description')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('apoyo_config_motivos', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 160);
            $table->string('area_slug', 80)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['area_slug', 'active'], 'apoyo_config_motivos_area_active_idx');
        });

        Schema::create('apoyo_atenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('teacher_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('apoyo_profesional_id')->nullable()->constrained('apoyo_profesionales')->nullOnDelete();
            $table->foreignId('attended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('attention_type_id')->nullable()->constrained('apoyo_config_tipos_atencion')->nullOnDelete();
            $table->foreignId('motive_id')->nullable()->constrained('apoyo_config_motivos')->nullOnDelete();
            $table->dateTime('attended_at');
            $table->string('professional_role_name', 120);
            $table->string('professional_area_slug', 80)->nullable();
            $table->string('professional_area_name', 120)->nullable();
            $table->string('student_full_name_snapshot', 160);
            $table->string('student_rut_snapshot', 20)->nullable();
            $table->string('course_name_snapshot', 160)->nullable();
            $table->string('teacher_name_snapshot', 160)->nullable();
            $table->unsignedSmallInteger('age_snapshot')->nullable();
            $table->string('motive_label', 160)->nullable();
            $table->string('attention_type_label', 120)->nullable();
            $table->string('attention_type_other', 160)->nullable();
            $table->string('modality', 80);
            $table->string('modality_other', 160)->nullable();
            $table->string('origin', 120);
            $table->string('origin_other', 160)->nullable();
            $table->string('priority_level', 40)->default('media');
            $table->string('confidentiality_level', 40)->default('general');
            $table->text('reason_summary');
            $table->longText('description')->nullable();
            $table->longText('professional_observations')->nullable();
            $table->longText('agreements')->nullable();
            $table->longText('recommendations')->nullable();
            $table->longText('next_action')->nullable();
            $table->string('status', 40)->default('abierta');
            $table->dateTime('case_closed_at')->nullable();
            $table->foreignId('case_closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('case_closed_notes')->nullable();
            $table->dateTime('escalated_to_direction_at')->nullable();
            $table->dateTime('derived_to_convivencia_at')->nullable();
            $table->dateTime('derived_to_pie_at')->nullable();
            $table->boolean('is_confidential_case')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_profile_id', 'attended_at'], 'apoyo_atenciones_student_date_idx');
            $table->index(['status', 'priority_level'], 'apoyo_atenciones_status_priority_idx');
            $table->index(['confidentiality_level', 'attended_at'], 'apoyo_atenciones_confidentiality_idx');
            $table->index(['professional_area_slug', 'attended_at'], 'apoyo_atenciones_area_date_idx');
        });

        Schema::create('apoyo_derivaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attention_id')->constrained('apoyo_atenciones')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('destination_professional_id')->nullable()->constrained('apoyo_profesionales')->nullOnDelete();
            $table->foreignId('destination_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin_area_slug', 80)->nullable();
            $table->string('origin_area_name', 120)->nullable();
            $table->string('destination_area_slug', 80);
            $table->string('destination_area_name', 120);
            $table->string('urgency_level', 40)->default('media');
            $table->string('confidentiality_level', 40)->default('general');
            $table->string('status', 40)->default('enviada');
            $table->text('reason');
            $table->longText('description')->nullable();
            $table->longText('destination_response')->nullable();
            $table->dateTime('derived_at');
            $table->dateTime('response_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'derived_at'], 'apoyo_derivaciones_status_date_idx');
            $table->index(['destination_area_slug', 'status'], 'apoyo_derivaciones_area_status_idx');
        });

        Schema::create('apoyo_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attention_id')->constrained('apoyo_atenciones')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('responsible_professional_id')->nullable()->constrained('apoyo_profesionales')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->longText('comment');
            $table->string('status', 40)->default('pendiente');
            $table->longText('next_action')->nullable();
            $table->longText('evidence_summary')->nullable();
            $table->longText('result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at'], 'apoyo_seguimientos_status_schedule_idx');
            $table->index(['student_profile_id', 'scheduled_at'], 'apoyo_seguimientos_student_schedule_idx');
        });

        Schema::create('apoyo_planes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('responsible_professional_id')->nullable()->constrained('apoyo_profesionales')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('area_slug', 80)->nullable();
            $table->string('area_name', 120)->nullable();
            $table->string('motive', 191);
            $table->longText('general_objective');
            $table->json('specific_objectives')->nullable();
            $table->longText('actions_summary')->nullable();
            $table->longText('responsibles_summary')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->longText('indicators')->nullable();
            $table->string('status', 40)->default('disenado');
            $table->longText('evidences')->nullable();
            $table->longText('observations')->nullable();
            $table->string('confidentiality_level', 40)->default('reservada');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_date'], 'apoyo_planes_status_start_idx');
            $table->index(['student_profile_id', 'status'], 'apoyo_planes_student_status_idx');
        });

        Schema::create('apoyo_plan_acciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('apoyo_planes')->cascadeOnDelete();
            $table->string('action_description', 191);
            $table->string('responsible_label', 160)->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status', 40)->default('pendiente');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'status'], 'apoyo_plan_acciones_plan_status_idx');
        });

        Schema::create('apoyo_entrevistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained('apoyo_profesionales')->nullOnDelete();
            $table->foreignId('professional_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('interview_type', 80);
            $table->dateTime('interview_at');
            $table->json('participants')->nullable();
            $table->text('motive');
            $table->longText('topics')->nullable();
            $table->longText('agreements')->nullable();
            $table->longText('commitments')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('status', 40)->default('abierta');
            $table->string('confidentiality_level', 40)->default('reservada');
            $table->longText('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['interview_type', 'interview_at'], 'apoyo_entrevistas_type_date_idx');
            $table->index(['status', 'follow_up_date'], 'apoyo_entrevistas_status_follow_up_idx');
        });

        Schema::create('apoyo_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('documentable');
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->string('category', 80)->default('otro');
            $table->string('confidentiality_level', 40)->default('general');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_profile_id', 'category'], 'apoyo_adjuntos_student_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apoyo_adjuntos');
        Schema::dropIfExists('apoyo_entrevistas');
        Schema::dropIfExists('apoyo_plan_acciones');
        Schema::dropIfExists('apoyo_planes');
        Schema::dropIfExists('apoyo_seguimientos');
        Schema::dropIfExists('apoyo_derivaciones');
        Schema::dropIfExists('apoyo_atenciones');
        Schema::dropIfExists('apoyo_config_motivos');
        Schema::dropIfExists('apoyo_config_tipos_atencion');
        Schema::dropIfExists('apoyo_profesionales');
    }
};
