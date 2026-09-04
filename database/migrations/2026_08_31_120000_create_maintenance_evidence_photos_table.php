<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_evidence_photos')) {
            return;
        }

        Schema::create('maintenance_evidence_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_visit_checklist_response_id')
                ->nullable()
                ->constrained('maintenance_visit_checklist_responses', 'id', 'mep_response_fk')
                ->cascadeOnDelete();
            $table->foreignId('maintenance_work_order_id')
                ->nullable()
                ->constrained('maintenance_work_orders', 'id', 'mep_work_order_fk')
                ->nullOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->constrained('users', 'id', 'mep_uploaded_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['maintenance_visit_checklist_response_id', 'created_at'],
                'mep_response_created_idx'
            );
            $table->index(['maintenance_work_order_id', 'created_at'], 'mep_work_order_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_evidence_photos');
    }
};
