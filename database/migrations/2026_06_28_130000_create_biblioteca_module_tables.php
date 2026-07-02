<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblioteca_obras', function (Blueprint $table) {
            $table->id();
            $table->string('material_type', 80)->default('libro')->index();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('main_author', 191)->index();
            $table->json('secondary_authors')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('isbn', 50)->nullable()->index();
            $table->string('category', 120)->nullable()->index();
            $table->string('subcategory', 120)->nullable();
            $table->string('genre', 120)->nullable();
            $table->string('recommended_level', 120)->nullable();
            $table->foreignId('recommended_course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->string('language', 80)->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->text('description')->nullable();
            $table->json('keywords')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('internal_code', 80)->unique();
            $table->string('barcode', 120)->nullable()->unique();
            $table->string('physical_location', 120)->nullable();
            $table->string('shelf', 120)->nullable();
            $table->string('section', 120)->nullable();
            $table->string('general_status', 60)->default('disponible')->index();
            $table->text('observations')->nullable();
            $table->unsignedInteger('total_copies')->default(0);
            $table->unsignedInteger('available_copies')->default(0);
            $table->unsignedInteger('loan_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_type', 'category'], 'bib_obras_type_cat_idx');
            $table->index(['general_status', 'available_copies'], 'bib_obras_status_avail_idx');
        });

        Schema::create('biblioteca_ejemplares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblioteca_obra_id')->constrained('biblioteca_obras')->cascadeOnDelete();
            $table->string('code', 80)->unique();
            $table->string('barcode', 120)->nullable()->unique();
            $table->date('ingress_date')->nullable();
            $table->string('origin', 80)->default('inventario_inicial')->index();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->string('physical_location', 120)->nullable();
            $table->string('physical_state', 60)->default('bueno')->index();
            $table->string('availability_status', 60)->default('disponible')->index();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->json('photo_urls')->nullable();
            $table->date('last_inventory_checked_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('lost_at')->nullable();
            $table->timestamp('damaged_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['biblioteca_obra_id', 'availability_status'], 'bib_ejem_obra_avail_idx');
            $table->index(['physical_state', 'is_active'], 'bib_ejem_state_active_idx');
        });

        Schema::create('biblioteca_prestamos', function (Blueprint $table) {
            $table->id();
            $table->string('loan_code', 80)->unique();
            $table->string('batch_code', 80)->nullable()->index();
            $table->string('borrower_type', 40)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('biblioteca_obra_id')->constrained('biblioteca_obras')->cascadeOnDelete();
            $table->foreignId('biblioteca_ejemplar_id')->constrained('biblioteca_ejemplares')->restrictOnDelete();
            $table->string('borrower_name_snapshot');
            $table->string('course_name_snapshot')->nullable();
            $table->dateTime('borrowed_at');
            $table->date('due_at');
            $table->dateTime('returned_at')->nullable();
            $table->string('status', 60)->default('activo')->index();
            $table->unsignedTinyInteger('renewed_count')->default(0);
            $table->integer('overdue_days')->default(0);
            $table->string('returned_condition', 60)->nullable();
            $table->text('notes')->nullable();
            $table->json('audit_trail')->nullable();
            $table->timestamp('lost_reported_at')->nullable();
            $table->timestamp('damaged_reported_at')->nullable();
            $table->foreignId('delivered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_at'], 'bib_pres_status_due_idx');
            $table->index(['student_profile_id', 'status'], 'bib_pres_student_status_idx');
            $table->index(['staff_id', 'status'], 'bib_pres_staff_status_idx');
            $table->index(['course_section_id', 'status'], 'bib_pres_course_status_idx');
        });

        Schema::create('biblioteca_reservas', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_code', 80)->unique();
            $table->string('resource_type', 80)->index();
            $table->foreignId('biblioteca_obra_id')->nullable()->constrained('biblioteca_obras')->nullOnDelete();
            $table->foreignId('biblioteca_ejemplar_id')->nullable()->constrained('biblioteca_ejemplares')->nullOnDelete();
            $table->foreignId('biblioteca_prestamo_id')->nullable()->constrained('biblioteca_prestamos')->nullOnDelete();
            $table->string('requester_type', 40)->index();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->dateTime('requested_at');
            $table->dateTime('pickup_at')->nullable();
            $table->dateTime('expected_return_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('purpose')->nullable();
            $table->string('status', 60)->default('solicitada')->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'pickup_at'], 'bib_res_status_pickup_idx');
            $table->index(['resource_type', 'status'], 'bib_res_type_status_idx');
        });

        Schema::create('biblioteca_plan_lector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->string('subject', 120);
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('biblioteca_obra_id')->constrained('biblioteca_obras')->cascadeOnDelete();
            $table->string('period', 80)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('objective')->nullable();
            $table->text('associated_activity')->nullable();
            $table->text('evaluation_description')->nullable();
            $table->unsignedInteger('required_copies')->default(1);
            $table->unsignedInteger('available_copies')->default(0);
            $table->unsignedTinyInteger('fulfillment_percentage')->default(0);
            $table->string('status', 60)->default('planificado')->index();
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'course_section_id'], 'bib_plan_year_course_idx');
            $table->index(['status', 'start_date'], 'bib_plan_status_start_idx');
        });

        Schema::create('biblioteca_espacios', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('location', 191)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->json('resources')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('biblioteca_uso_espacios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblioteca_espacio_id')->constrained('biblioteca_espacios')->cascadeOnDelete();
            $table->string('activity_type', 80)->index();
            $table->string('title');
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('attendee_count')->nullable();
            $table->json('requested_resources')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status', 60)->default('solicitada')->index();
            $table->text('observations')->nullable();
            $table->json('evidence')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['biblioteca_espacio_id', 'start_at'], 'bib_uso_espacio_start_idx');
            $table->index(['status', 'start_at'], 'bib_uso_status_start_idx');
        });

        Schema::create('biblioteca_inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblioteca_ejemplar_id')->constrained('biblioteca_ejemplares')->cascadeOnDelete();
            $table->string('movement_type', 80)->index();
            $table->string('previous_location', 191)->nullable();
            $table->string('new_location', 191)->nullable();
            $table->string('previous_state', 80)->nullable();
            $table->string('new_state', 80)->nullable();
            $table->dateTime('movement_date');
            $table->string('physical_count_status', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['biblioteca_ejemplar_id', 'movement_date'], 'bib_inv_ejem_date_idx');
        });

        Schema::create('biblioteca_alertas', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type', 80)->index();
            $table->string('alert_level', 40)->default('info')->index();
            $table->string('title');
            $table->text('message');
            $table->string('status', 40)->default('pendiente')->index();
            $table->dateTime('due_at')->nullable();
            $table->string('related_type', 120)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('recipient_scope', 80)->nullable();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['related_type', 'related_id'], 'bib_alert_related_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblioteca_alertas');
        Schema::dropIfExists('biblioteca_inventario_movimientos');
        Schema::dropIfExists('biblioteca_uso_espacios');
        Schema::dropIfExists('biblioteca_espacios');
        Schema::dropIfExists('biblioteca_plan_lector');
        Schema::dropIfExists('biblioteca_reservas');
        Schema::dropIfExists('biblioteca_prestamos');
        Schema::dropIfExists('biblioteca_ejemplares');
        Schema::dropIfExists('biblioteca_obras');
    }
};
