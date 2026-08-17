<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_curriculum_sources')) {
            Schema::create('lcd_curriculum_sources', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('curriculum_catalog_id')->constrained('lcd_curriculum_catalogs')->restrictOnDelete();
                $table->foreignId('normative_source_id')->constrained('lcd_normative_sources')->restrictOnDelete();
                $table->string('source_key', 100);
                $table->string('source_scope', 40);
                $table->string('source_name');
                $table->string('authority', 160);
                $table->string('document_number', 100);
                $table->text('source_url');
                $table->char('declared_sha256', 64);
                $table->char('verified_sha256', 64);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('curriculum_track', 30)->nullable();
                $table->foreignId('schedule_subject_id')->nullable()->constrained('schedule_subjects')->restrictOnDelete();
                $table->string('objective_type', 20)->nullable();
                $table->string('status', 40)->default('verified');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_catalog_id', 'source_key'], 'lcd_curriculum_sources_catalog_key_uq');
                $table->index(['curriculum_catalog_id', 'source_scope', 'status'], 'lcd_curriculum_sources_scope_idx');
                $table->index(['declared_sha256', 'verified_sha256'], 'lcd_curriculum_sources_hash_idx');
            });
        }

        if (! Schema::hasTable('lcd_learning_objective_sources')) {
            Schema::create('lcd_learning_objective_sources', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('learning_objective_id')->constrained('lcd_learning_objectives')->restrictOnDelete();
                $table->foreignId('curriculum_source_id')->constrained('lcd_curriculum_sources')->restrictOnDelete();
                $table->string('source_role', 30);
                $table->string('source_locator', 160);
                $table->char('relationship_hash', 64);
                $table->json('source_snapshot');
                $table->timestamps();

                $table->unique(
                    ['learning_objective_id', 'curriculum_source_id', 'source_role', 'source_locator'],
                    'lcd_objective_sources_identity_uq'
                );
                $table->index(['learning_objective_id', 'source_role'], 'lcd_objective_sources_role_idx');
                $table->index(['curriculum_source_id', 'source_role'], 'lcd_objective_sources_source_idx');
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: fuentes, relaciones y evidencia curricular nunca se eliminan por rollback.
    }
};
