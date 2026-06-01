<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('maintenance_visit_checklist_responses')) {
            return;
        }

        $database = DB::connection()->getDatabaseName();

        $hasForeignForColumn = function (string $column) use ($database): bool {
            try {
                $row = DB::table('information_schema.KEY_COLUMN_USAGE')
                    ->where('TABLE_SCHEMA', $database)
                    ->where('TABLE_NAME', 'maintenance_visit_checklist_responses')
                    ->where('COLUMN_NAME', $column)
                    ->whereNotNull('REFERENCED_TABLE_NAME')
                    ->selectRaw('1')
                    ->first();
            } catch (QueryException $e) {
                // Si el usuario no puede leer information_schema, asumimos que no existe y
                // dejamos que el ALTER TABLE falle con un error real si corresponde.
                return false;
            }

            return (bool) $row;
        };

        Schema::table('maintenance_visit_checklist_responses', function (Blueprint $table) use ($hasForeignForColumn) {
            // Si la migración original falló por nombres largos, la tabla puede existir
            // sin las FK. Agregamos FK con nombres cortos e ignoramos si ya existen.
            if (Schema::hasColumn('maintenance_visit_checklist_responses', 'maintenance_visit_id')
                && Schema::hasColumn('maintenance_visit_checklist_responses', 'maintenance_checklist_item_id')
                && !Schema::hasIndex('maintenance_visit_checklist_responses', ['maintenance_visit_id', 'maintenance_checklist_item_id'], 'unique')
            ) {
                $table->unique(['maintenance_visit_id', 'maintenance_checklist_item_id'], 'visit_item_unique');
            }

            if (Schema::hasColumn('maintenance_visit_checklist_responses', 'maintenance_visit_id')
                && Schema::hasColumn('maintenance_visit_checklist_responses', 'review_status')
                && !Schema::hasIndex('maintenance_visit_checklist_responses', ['maintenance_visit_id', 'review_status'])
            ) {
                $table->index(['maintenance_visit_id', 'review_status'], 'mvcr_visit_review_idx');
            }

            if (Schema::hasColumn('maintenance_visit_checklist_responses', 'maintenance_visit_id') && !$hasForeignForColumn('maintenance_visit_id')) {
                try {
                    $table
                        ->foreign('maintenance_visit_id', 'mvcr_visit_fk')
                        ->references('id')
                        ->on('maintenance_visits')
                        ->cascadeOnDelete();
                } catch (QueryException $e) {
                    // Ignorar si ya existe o no se puede crear por duplicado.
                    if (($e->errorInfo[1] ?? null) !== 1826) {
                        throw $e;
                    }
                }
            }

            if (Schema::hasColumn('maintenance_visit_checklist_responses', 'maintenance_checklist_item_id') && !$hasForeignForColumn('maintenance_checklist_item_id')) {
                try {
                    $table
                        ->foreign('maintenance_checklist_item_id', 'mvcr_item_fk')
                        ->references('id')
                        ->on('maintenance_checklist_items')
                        ->cascadeOnDelete();
                } catch (QueryException $e) {
                    if (($e->errorInfo[1] ?? null) !== 1826) {
                        throw $e;
                    }
                }
            }

            if (Schema::hasColumn('maintenance_visit_checklist_responses', 'work_order_id') && !$hasForeignForColumn('work_order_id')) {
                try {
                    $table
                        ->foreign('work_order_id', 'mvcr_work_order_fk')
                        ->references('id')
                        ->on('maintenance_work_orders')
                        ->nullOnDelete();
                } catch (QueryException $e) {
                    if (($e->errorInfo[1] ?? null) !== 1826) {
                        throw $e;
                    }
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenance_visit_checklist_responses')) {
            return;
        }

        Schema::table('maintenance_visit_checklist_responses', function (Blueprint $table) {
            // Estos nombres son los que usamos para evitar límites de MySQL.
            try {
                $table->dropForeign('mvcr_visit_fk');
            } catch (Throwable $e) {
                // ignore
            }

            try {
                $table->dropForeign('mvcr_item_fk');
            } catch (Throwable $e) {
                // ignore
            }

            try {
                $table->dropForeign('mvcr_work_order_fk');
            } catch (Throwable $e) {
                // ignore
            }
        });
    }
};
