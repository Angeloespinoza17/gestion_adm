<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $planColumns = Schema::getColumnListing('convivencia_plans');
        Schema::table('convivencia_plans', function (Blueprint $table) use ($planColumns): void {
            if (! in_array('calendar_year', $planColumns, true)) {
                $table->unsignedSmallInteger('calendar_year')->nullable()->unique('convivencia_plans_calendar_year_unique');
            }
            if (! in_array('version_number', $planColumns, true)) {
                $table->unsignedInteger('version_number')->default(1);
            }
            if (! in_array('revision', $planColumns, true)) {
                $table->unsignedInteger('revision')->default(1);
            }
            if (! in_array('source_document_name', $planColumns, true)) {
                $table->string('source_document_name', 255)->nullable();
            }
            if (! in_array('source_document_sha256', $planColumns, true)) {
                $table->string('source_document_sha256', 64)->nullable();
            }
            if (! in_array('institutional_protocol', $planColumns, true)) {
                $table->json('institutional_protocol')->nullable();
            }
            if (! in_array('evaluation_indicators', $planColumns, true)) {
                $table->json('evaluation_indicators')->nullable();
            }
            if (! in_array('regulatory_linkage_text', $planColumns, true)) {
                $table->longText('regulatory_linkage_text')->nullable();
            }
            if (! in_array('regulatory_review_required', $planColumns, true)) {
                $table->boolean('regulatory_review_required')->default(false);
            }
            if (! in_array('approved_at', $planColumns, true)) {
                $table->dateTime('approved_at')->nullable();
            }
            if (! in_array('approved_by', $planColumns, true)) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        $actionColumns = Schema::getColumnListing('convivencia_plan_actions');
        Schema::table('convivencia_plan_actions', function (Blueprint $table) use ($actionColumns): void {
            if (! in_array('objective', $actionColumns, true)) {
                $table->text('objective')->nullable();
            }
            if (! in_array('target_audience', $actionColumns, true)) {
                $table->text('target_audience')->nullable();
            }
            if (! in_array('planned_month', $actionColumns, true)) {
                $table->unsignedTinyInteger('planned_month')->nullable();
            }
            if (! in_array('date_precision', $actionColumns, true)) {
                $table->string('date_precision', 20)->default('exact');
            }
            if (! in_array('sort_order', $actionColumns, true)) {
                $table->unsignedSmallInteger('sort_order')->default(1);
            }
            if (! in_array('deleted_at', $actionColumns, true)) {
                $table->softDeletes();
            }
        });

        if (! Schema::hasTable('convivencia_plan_versions')) {
            Schema::create('convivencia_plan_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plan_id')->constrained('convivencia_plans')->cascadeOnDelete();
                $table->unsignedInteger('version_number');
                $table->string('change_summary', 500);
                $table->json('snapshot');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['plan_id', 'version_number'], 'convivencia_plan_version_unique');
                $table->index(['plan_id', 'created_at'], 'convivencia_plan_version_history_idx');
            });
        }

        if (! Schema::hasTable('convivencia_plan_activities')) {
            Schema::create('convivencia_plan_activities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plan_action_id')->constrained('convivencia_plan_actions')->cascadeOnDelete();
                $table->string('title', 191);
                $table->longText('description')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at')->nullable();
                $table->string('status', 30)->default('programada');
                $table->unsignedTinyInteger('contribution_percent')->default(0);
                $table->unsignedTinyInteger('completion_percent')->default(0);
                $table->unsignedInteger('revision')->default(1);
                $table->string('location', 191)->nullable();
                $table->text('target_audience')->nullable();
                $table->unsignedInteger('attendee_count')->nullable();
                $table->longText('results')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['plan_action_id', 'status', 'starts_at'], 'convivencia_plan_activity_action_idx');
                $table->index(['starts_at', 'ends_at'], 'convivencia_plan_activity_calendar_idx');
            });
        }
    }

    public function down(): void
    {
        // Migración de gestión documental: no elimina versiones, actividades ni datos del plan.
    }
};
