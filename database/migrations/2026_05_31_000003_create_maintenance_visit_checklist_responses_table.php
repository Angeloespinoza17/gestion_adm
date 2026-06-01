<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_visit_checklist_responses')) {
            return;
        }

        try {
            Schema::create('maintenance_visit_checklist_responses', function (Blueprint $table) {
                $table->id();
                // MySQL limita el nombre del constraint a 64 caracteres. Laravel genera nombres largos
                // por defecto, así que definimos nombres cortos para evitar el error 1059.
                $table->foreignId('maintenance_visit_id')
                    ->constrained('maintenance_visits', 'id', 'mvcr_visit_fk')
                    ->cascadeOnDelete();

                $table->foreignId('maintenance_checklist_item_id')
                    ->constrained('maintenance_checklist_items', 'id', 'mvcr_item_fk')
                    ->cascadeOnDelete();

                $table->string('review_status')->nullable(); // OK | No OK | N/A
                $table->text('observations')->nullable();
                $table->text('finding_description')->nullable();
                $table->string('photo_reference')->nullable();
                $table->foreignId('work_order_id')
                    ->nullable()
                    ->constrained('maintenance_work_orders', 'id', 'mvcr_work_order_fk')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique(['maintenance_visit_id', 'maintenance_checklist_item_id'], 'visit_item_unique');
                $table->index(['maintenance_visit_id', 'review_status'], 'mvcr_visit_review_idx');
            });
        } catch (QueryException $e) {
            // En algunos entornos `hasTable()` puede devolver false por permisos/cache,
            // pero MySQL igual rechaza el CREATE TABLE con 1050 (table exists).
            // Si ya existe, dejamos pasar la migración.
            if (($e->errorInfo[1] ?? null) === 1050 || str_contains($e->getMessage(), 'already exists')) {
                return;
            }

            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_visit_checklist_responses');
    }
};
