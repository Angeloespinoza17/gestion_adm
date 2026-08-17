<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_work_program_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('category', 60)->index();
            $table->boolean('active')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('student_social_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('program_type_id')->constrained('social_work_program_types')->restrictOnDelete();
            $table->unsignedSmallInteger('school_year')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 30)->default('vigente')->index();
            $table->text('notes')->nullable();
            $table->string('confidentiality', 30)->default('interno');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_profile_id', 'status']);
            $table->index(['school_year', 'program_type_id']);
        });

        Schema::create('student_protection_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->nullOnDelete();
            $table->string('measure_type', 100)->index();
            $table->string('authority')->nullable();
            $table->string('reference_number', 80)->nullable();
            $table->date('starts_on')->index();
            $table->date('ends_on')->nullable()->index();
            $table->string('status', 30)->default('vigente')->index();
            $table->text('school_scope')->nullable();
            $table->string('confidentiality', 30)->default('altamente_restringido');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_profile_id', 'status']);
        });

        Schema::create('junaeb_benefit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('category', 60)->index();
            $table->boolean('active')->default(true);
            $table->json('eligible_level_codes')->nullable();
            $table->unsignedSmallInteger('school_year')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('student_junaeb_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('benefit_type_id')->constrained('junaeb_benefit_types')->restrictOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->unsignedSmallInteger('school_year')->index();
            $table->string('status', 30)->default('pendiente')->index();
            $table->date('approved_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['student_profile_id', 'benefit_type_id', 'school_year'], 'sw_junaeb_student_benefit_year_uq');
        });

        Schema::create('junaeb_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 60)->unique();
            $table->foreignId('student_junaeb_benefit_id')->constrained('student_junaeb_benefits')->restrictOnDelete();
            $table->date('received_by_school_on')->nullable();
            $table->date('delivered_on')->nullable()->index();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_relationship', 80)->nullable();
            $table->string('status', 30)->default('preparada')->index();
            $table->text('notes')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature_path')->nullable();
            $table->foreignId('cloned_from_id')->nullable()->constrained('junaeb_deliveries')->nullOnDelete();
            $table->foreignId('template_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('junaeb_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('junaeb_deliveries')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->default('unidad');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_transport_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->unsignedSmallInteger('school_year')->index();
            $table->string('pass_type', 60);
            $table->string('status', 40)->default('pendiente_solicitud')->index();
            $table->string('identifier', 100)->nullable();
            $table->date('requested_on')->nullable();
            $table->date('received_on')->nullable();
            $table->date('delivered_on')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['student_profile_id', 'school_year', 'pass_type'], 'sw_transport_pass_student_year_type_uq');
        });

        Schema::create('student_medical_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->string('service_type', 80)->index();
            $table->string('origin', 80)->nullable();
            $table->boolean('is_junaeb')->default(false)->index();
            $table->date('referred_on')->nullable();
            $table->date('attended_on')->nullable();
            $table->string('status', 30)->default('pendiente')->index();
            $table->text('general_result')->nullable();
            $table->date('next_control_on')->nullable()->index();
            $table->string('provider')->nullable();
            $table->text('notes')->nullable();
            $table->string('confidentiality', 30)->default('restringido');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('student_support_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->string('support_type', 80)->index();
            $table->string('origin', 60)->nullable();
            $table->date('starts_on')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->date('renewal_on')->nullable()->index();
            $table->string('brief_note')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('student_medical_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->nullOnDelete();
            $table->date('issued_on');
            $table->date('covers_from')->nullable();
            $table->date('covers_to')->nullable();
            $table->string('issuer')->nullable();
            $table->string('certificate_type', 80)->index();
            $table->text('administrative_summary')->nullable();
            $table->text('school_restrictions')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->string('status', 30)->default('vigente')->index();
            $table->string('private_path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('confidentiality', 30)->default('altamente_restringido');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_medical_certificates');
        Schema::dropIfExists('student_support_devices');
        Schema::dropIfExists('student_medical_services');
        Schema::dropIfExists('student_transport_passes');
        Schema::dropIfExists('junaeb_delivery_items');
        Schema::dropIfExists('junaeb_deliveries');
        Schema::dropIfExists('student_junaeb_benefits');
        Schema::dropIfExists('junaeb_benefit_types');
        Schema::dropIfExists('student_protection_measures');
        Schema::dropIfExists('student_social_programs');
        Schema::dropIfExists('social_work_program_types');
    }
};
