<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_apuntes_asignaturas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 80)->unique();
            $table->string('area', 120)->nullable()->index();
            $table->string('education_level', 120)->nullable()->index();
            $table->string('status', 40)->default('activa')->index();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'area'], 'ca_subject_status_area_idx');
        });

        Schema::create('centro_apuntes_maquinas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('internal_code', 80)->unique();
            $table->string('type', 80)->index();
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('location', 160)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('activa')->index();
            $table->decimal('estimated_cost_letter', 10, 2)->default(0);
            $table->decimal('estimated_cost_officio', 10, 2)->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'type'], 'ca_machine_status_type_idx');
        });

        Schema::create('centro_apuntes_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 80)->unique();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('requested_by_name_snapshot');
            $table->foreignId('subject_id')->constrained('centro_apuntes_asignaturas')->restrictOnDelete();
            $table->string('subject_name_snapshot');
            $table->foreignId('machine_id')->constrained('centro_apuntes_maquinas')->restrictOnDelete();
            $table->string('machine_name_snapshot');
            $table->string('task_type', 80)->index();
            $table->string('task_type_other', 191)->nullable();
            $table->dateTime('requested_at');
            $table->date('delivery_date')->index();
            $table->unsignedInteger('sheet_count');
            $table->unsignedInteger('copies_count');
            $table->string('paper_size', 40)->index();
            $table->string('priority', 40)->default('normal')->index();
            $table->boolean('is_urgent')->default(false)->index();
            $table->boolean('is_immediate')->default(false)->index();
            $table->text('instructions')->nullable();
            $table->text('observations')->nullable();
            $table->text('internal_observations')->nullable();
            $table->string('status', 60)->default('pendiente')->index();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('received_by_name_snapshot')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('status_changed_at')->nullable();
            $table->unsignedBigInteger('estimated_total_impressions')->default(0);
            $table->decimal('estimated_cost_per_sheet', 10, 2)->default(0);
            $table->decimal('estimated_cost_per_copy', 10, 2)->default(0);
            $table->decimal('estimated_cost_total', 12, 2)->default(0);
            $table->unsignedSmallInteger('attachment_count')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'delivery_date'], 'ca_request_status_delivery_idx');
            $table->index(['priority', 'is_urgent', 'is_immediate'], 'ca_request_priority_flags_idx');
            $table->index(['requested_by_user_id', 'requested_at'], 'ca_request_user_date_idx');
            $table->index(['subject_id', 'requested_at'], 'ca_request_subject_date_idx');
            $table->index(['machine_id', 'requested_at'], 'ca_request_machine_date_idx');
        });

        Schema::create('centro_apuntes_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('centro_apuntes_solicitudes')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('centro_apuntes_historial_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('centro_apuntes_solicitudes')->cascadeOnDelete();
            $table->string('action_type', 80)->index();
            $table->string('previous_status', 60)->nullable()->index();
            $table->string('new_status', 60)->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['solicitud_id', 'created_at'], 'ca_history_request_date_idx');
        });

        Schema::create('panol_insumos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 80)->index();
            $table->string('unit_of_measure', 40)->index();
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('minimum_stock', 12, 2)->default(0);
            $table->decimal('maximum_stock', 12, 2)->nullable();
            $table->string('location', 160)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('unit_price_estimated', 12, 2)->default(0);
            $table->date('last_purchase_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 40)->default('disponible')->index();
            $table->text('observations')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at'], 'panol_supply_status_exp_idx');
            $table->index(['current_stock', 'minimum_stock'], 'panol_supply_stock_min_idx');
        });

        Schema::create('panol_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insumo_id')->constrained('panol_insumos')->cascadeOnDelete();
            $table->string('movement_type', 40)->index();
            $table->decimal('quantity', 12, 2);
            $table->decimal('stock_before', 12, 2)->default(0);
            $table->decimal('stock_after', 12, 2)->default(0);
            $table->dateTime('moved_at')->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('reason', 191)->nullable();
            $table->string('document_reference', 191)->nullable();
            $table->text('observations')->nullable();
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['insumo_id', 'moved_at'], 'panol_movement_supply_date_idx');
            $table->index(['reference_type', 'reference_id'], 'panol_movement_ref_idx');
        });

        Schema::create('panol_entregas', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_code', 80)->unique();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('requested_by_name_snapshot');
            $table->foreignId('withdrawn_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('withdrawn_by_name_snapshot')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('department_name_snapshot', 160)->nullable();
            $table->dateTime('requested_at')->index();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('solicitada')->index();
            $table->decimal('total_estimated_cost', 12, 2)->default(0);
            $table->text('observations')->nullable();
            $table->text('receipt_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'requested_at'], 'panol_delivery_status_date_idx');
            $table->index(['department_id', 'requested_at'], 'panol_delivery_department_date_idx');
        });

        Schema::create('panol_entrega_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panol_entrega_id')->constrained('panol_entregas')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('panol_insumos')->restrictOnDelete();
            $table->string('insumo_name_snapshot');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost_estimated', 12, 2)->default(0);
            $table->decimal('line_total_estimated', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('centro_apuntes_alertas', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type', 80)->index();
            $table->string('alert_level', 40)->default('info')->index();
            $table->string('title');
            $table->text('message');
            $table->string('status', 40)->default('pendiente')->index();
            $table->string('related_type', 120)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('detected_at')->index();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id'], 'ca_alert_related_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_apuntes_alertas');
        Schema::dropIfExists('panol_entrega_detalles');
        Schema::dropIfExists('panol_entregas');
        Schema::dropIfExists('panol_movimientos');
        Schema::dropIfExists('panol_insumos');
        Schema::dropIfExists('centro_apuntes_historial_estados');
        Schema::dropIfExists('centro_apuntes_adjuntos');
        Schema::dropIfExists('centro_apuntes_solicitudes');
        Schema::dropIfExists('centro_apuntes_maquinas');
        Schema::dropIfExists('centro_apuntes_asignaturas');
    }
};
