<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lcd_learning_objectives')) {
            Schema::table('lcd_learning_objectives', function (Blueprint $table) {
                if (! Schema::hasColumn('lcd_learning_objectives', 'source_page')) {
                    $table->string('source_page', 80)->nullable();
                }
                if (! Schema::hasColumn('lcd_learning_objectives', 'source_row_hash')) {
                    $table->char('source_row_hash', 64)->nullable();
                }
            });

            if (! Schema::hasIndex('lcd_learning_objectives', 'lcd_learning_objectives_catalog_row_hash_idx')) {
                Schema::table('lcd_learning_objectives', function (Blueprint $table) {
                    $table->index(
                        ['curriculum_catalog_id', 'source_row_hash'],
                        'lcd_learning_objectives_catalog_row_hash_idx'
                    );
                });
            }
        }

        if (Schema::hasTable('lcd_subject_curriculum_links')) {
            if (! Schema::hasColumn('lcd_subject_curriculum_links', 'scope_key')) {
                Schema::table('lcd_subject_curriculum_links', function (Blueprint $table) {
                    $table->string('scope_key', 130)->default('ALL');
                });
            }

            if (! Schema::hasIndex('lcd_subject_curriculum_links', 'lcd_subject_curriculum_scoped_uq')) {
                Schema::table('lcd_subject_curriculum_links', function (Blueprint $table) {
                    $table->unique(
                        ['school_id', 'academic_year_id', 'schedule_subject_id', 'curriculum_catalog_id', 'scope_key'],
                        'lcd_subject_curriculum_scoped_uq'
                    );
                });
            }

            // Create the replacement first: MySQL may use the legacy unique index
            // to support the school foreign key and refuses to drop it otherwise.
            if (Schema::hasIndex('lcd_subject_curriculum_links', 'lcd_subject_curriculum_scope_uq')) {
                Schema::table('lcd_subject_curriculum_links', function (Blueprint $table) {
                    $table->dropUnique('lcd_subject_curriculum_scope_uq');
                });
            }

            if (! Schema::hasIndex('lcd_subject_curriculum_links', 'lcd_subject_curriculum_scope_dates_idx')) {
                Schema::table('lcd_subject_curriculum_links', function (Blueprint $table) {
                    $table->index(
                        ['school_id', 'academic_year_id', 'scope_key', 'active', 'valid_from', 'valid_to'],
                        'lcd_subject_curriculum_scope_dates_idx'
                    );
                });
            }
        }

        if (! Schema::hasTable('lcd_curriculum_import_batches')) {
            Schema::create('lcd_curriculum_import_batches', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('curriculum_catalog_id')->nullable()->constrained('lcd_curriculum_catalogs')->restrictOnDelete();
                $table->foreignId('normative_source_id')->nullable()->constrained('lcd_normative_sources')->restrictOnDelete();
                $table->char('idempotency_key', 64);
                $table->string('status', 40)->default('uploaded');
                $table->unsignedInteger('lock_version')->default(1);
                $table->string('catalog_code', 100);
                $table->string('catalog_version', 50);
                $table->string('import_format', 40)->default('xlsx');
                $table->string('format_version', 50)->nullable();
                $table->string('original_name');
                $table->string('detected_mime_type', 160)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('disk', 80)->default('private');
                $table->string('private_path');
                $table->json('storage_metadata')->nullable();
                $table->char('source_hash', 64);
                $table->char('declared_source_hash', 64)->nullable();
                $table->json('manifest')->nullable();
                $table->char('manifest_hash', 64)->nullable();
                $table->longText('validated_payload_encrypted')->nullable();
                $table->json('validation_errors')->nullable();
                $table->unsignedBigInteger('total_row_count')->default(0);
                $table->unsignedBigInteger('catalog_row_count')->default(0);
                $table->unsignedBigInteger('objective_row_count')->default(0);
                $table->unsignedBigInteger('link_row_count')->default(0);
                $table->unsignedBigInteger('reference_row_count')->default(0);
                $table->unsignedBigInteger('valid_row_count')->default(0);
                $table->unsignedBigInteger('invalid_row_count')->default(0);
                $table->unsignedBigInteger('warning_count')->default(0);
                $table->unsignedBigInteger('error_count')->default(0);
                $table->unsignedBigInteger('imported_row_count')->default(0);
                $table->unsignedBigInteger('skipped_row_count')->default(0);
                $table->unsignedBigInteger('objective_count')->default(0);
                $table->unsignedBigInteger('oa_count')->default(0);
                $table->unsignedBigInteger('oat_count')->default(0);
                $table->unsignedBigInteger('subject_link_count')->default(0);
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('requested_at');
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('error_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(
                    ['school_id', 'academic_year_id', 'idempotency_key'],
                    'lcd_curr_import_scope_dedupe_uq'
                );
                $table->unique(
                    ['school_id', 'academic_year_id', 'source_hash'],
                    'lcd_curr_import_scope_hash_uq'
                );
                $table->index(
                    ['school_id', 'academic_year_id', 'status'],
                    'lcd_curr_import_scope_status_idx'
                );
                $table->index(
                    ['curriculum_catalog_id', 'status'],
                    'lcd_curr_import_catalog_status_idx'
                );
            });
        }

        if (! Schema::hasTable('lcd_curriculum_import_evidences')) {
            Schema::create('lcd_curriculum_import_evidences', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('import_batch_id')->constrained('lcd_curriculum_import_batches')->restrictOnDelete();
                $table->foreignId('curriculum_catalog_id')->nullable()->constrained('lcd_curriculum_catalogs')->restrictOnDelete();
                $table->string('evidence_kind', 60);
                $table->string('status', 40)->default('pending_verification');
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('source_url')->nullable();
                $table->string('original_name')->nullable();
                $table->string('detected_mime_type', 160)->nullable();
                $table->string('extension', 20)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('disk', 80)->default('private');
                $table->string('private_path')->nullable();
                $table->char('sha256', 64);
                $table->json('storage_metadata')->nullable();
                $table->json('manifest')->nullable();
                $table->string('confidentiality_level', 40)->default('restricted');
                $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('captured_at');
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->text('verification_notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(
                    ['import_batch_id', 'evidence_kind', 'sha256'],
                    'lcd_curr_evidence_batch_kind_hash_uq'
                );
                $table->index(
                    ['school_id', 'academic_year_id', 'status'],
                    'lcd_curr_evidence_scope_status_idx'
                );
                $table->index(
                    ['curriculum_catalog_id', 'evidence_kind'],
                    'lcd_curr_evidence_catalog_kind_idx'
                );
            });
        }

        if (! Schema::hasTable('lcd_curriculum_catalog_activations')) {
            Schema::create('lcd_curriculum_catalog_activations', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('curriculum_catalog_id')->constrained('lcd_curriculum_catalogs')->restrictOnDelete();
                $table->foreignId('import_batch_id')->constrained('lcd_curriculum_import_batches')->restrictOnDelete();
                $table->foreignId('supersedes_activation_id')->nullable();
                $table->foreign('supersedes_activation_id', 'lcd_curr_activation_supersedes_fk')
                    ->references('id')->on('lcd_curriculum_catalog_activations')->restrictOnDelete();
                $table->unsignedInteger('activation_version')->default(1);
                $table->char('idempotency_key', 64);
                $table->string('status', 40)->default('requested');
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->json('scope_snapshot');
                $table->json('decision_manifest')->nullable();
                $table->char('decision_hash', 64)->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('requested_at');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('activated_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('revoked_at')->nullable();
                $table->text('decision_notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('revocation_reason')->nullable();
                $table->timestamps();

                $table->unique(
                    ['school_id', 'academic_year_id', 'curriculum_catalog_id', 'activation_version'],
                    'lcd_curr_activation_scope_version_uq'
                );
                $table->unique(
                    ['school_id', 'academic_year_id', 'idempotency_key'],
                    'lcd_curr_activation_scope_dedupe_uq'
                );
                $table->index(
                    ['school_id', 'academic_year_id', 'status'],
                    'lcd_curr_activation_scope_status_idx'
                );
                $table->index(
                    ['curriculum_catalog_id', 'status', 'effective_from'],
                    'lcd_curr_activation_catalog_effective_idx'
                );
                $table->index(
                    ['import_batch_id', 'status'],
                    'lcd_curr_activation_batch_status_idx'
                );
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: curriculum evidence and approval history must never be removed by rollback.
    }
};
