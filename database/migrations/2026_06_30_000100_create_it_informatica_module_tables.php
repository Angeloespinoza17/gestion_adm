<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_equipment', function (Blueprint $table) {
            $table->id();
            $table->string('internal_code', 80)->unique();
            $table->string('equipment_type', 80)->index();
            $table->string('brand', 120)->nullable()->index();
            $table->string('model', 160)->nullable();
            $table->string('serial_number', 120)->nullable()->unique();
            $table->string('status', 60)->default('disponible')->index();
            $table->string('location_name', 191)->nullable()->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsible_name', 191)->nullable()->index();
            $table->date('acquisition_date')->nullable()->index();
            $table->decimal('reference_value', 12, 2)->nullable();
            $table->text('observations')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('photo_original_name')->nullable();
            $table->string('photo_mime_type', 160)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['equipment_type', 'status'], 'it_equipment_type_status_idx');
            $table->index(['location_name', 'status'], 'it_equipment_location_status_idx');
        });

        Schema::create('it_equipment_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_code', 80)->unique();
            $table->foreignId('it_equipment_id')->constrained('it_equipment')->restrictOnDelete();
            $table->string('requester_type', 40)->index();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requester_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('requester_student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->string('requester_name_snapshot', 191);
            $table->string('requester_rut_snapshot', 40)->nullable()->index();
            $table->string('requester_contact_snapshot', 191)->nullable();
            $table->dateTime('borrowed_at');
            $table->dateTime('due_at')->index();
            $table->dateTime('returned_at')->nullable();
            $table->text('purpose')->nullable();
            $table->string('location_name', 191)->nullable();
            $table->foreignId('delivered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('activo')->index();
            $table->string('return_condition', 60)->nullable();
            $table->text('notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['it_equipment_id', 'status'], 'it_loans_equipment_status_idx');
            $table->index(['requester_type', 'status'], 'it_loans_requester_status_idx');
            $table->index(['status', 'due_at'], 'it_loans_status_due_idx');
        });

        Schema::create('it_equipment_maintenance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('maintenance_code', 80)->unique();
            $table->foreignId('it_equipment_id')->constrained('it_equipment')->restrictOnDelete();
            $table->dateTime('maintenance_date')->index();
            $table->string('maintenance_type', 60)->index();
            $table->foreignId('technician_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('technician_name_snapshot', 191)->nullable()->index();
            $table->text('reason');
            $table->longText('diagnosis')->nullable();
            $table->longText('actions_performed')->nullable();
            $table->text('spare_parts')->nullable();
            $table->decimal('cost_amount', 12, 2)->nullable();
            $table->string('initial_equipment_status', 60);
            $table->string('final_equipment_status', 60)->nullable()->index();
            $table->date('next_maintenance_at')->nullable()->index();
            $table->text('observations')->nullable();
            $table->string('status', 60)->default('borrador')->index();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['it_equipment_id', 'status'], 'it_maintenance_equipment_status_idx');
            $table->index(['maintenance_type', 'status'], 'it_maintenance_type_status_idx');
        });

        Schema::create('it_equipment_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_equipment_id')->constrained('it_equipment')->cascadeOnDelete();
            $table->string('previous_status', 60)->nullable();
            $table->string('new_status', 60)->index();
            $table->dateTime('changed_at')->index();
            $table->string('source_type', 80)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'it_status_logs_source_idx');
            $table->index(['it_equipment_id', 'changed_at'], 'it_status_logs_equipment_changed_idx');
        });

        Schema::create('it_equipment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_equipment_id')->constrained('it_equipment')->cascadeOnDelete();
            $table->nullableMorphs('attachable');
            $table->string('category', 80)->default('documento')->index();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['it_equipment_id', 'category'], 'it_attachments_equipment_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_equipment_attachments');
        Schema::dropIfExists('it_equipment_status_logs');
        Schema::dropIfExists('it_equipment_maintenance_reports');
        Schema::dropIfExists('it_equipment_loans');
        Schema::dropIfExists('it_equipment');
    }
};
