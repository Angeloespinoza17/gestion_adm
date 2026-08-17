<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_ede_versions')) {
            Schema::create('lcd_ede_versions', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('code', 80);
                $table->string('version', 50);
                $table->string('authority', 160)->nullable();
                $table->text('source_url')->nullable();
                $table->char('source_hash', 64);
                $table->char('schema_hash', 64)->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('status', 30)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamp('imported_at')->nullable();
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['code', 'version'], 'lcd_ede_versions_code_version_uq');
                $table->index(['status', 'effective_from', 'effective_to'], 'lcd_ede_versions_effective_idx');
            });
        }

        if (! Schema::hasTable('lcd_ede_mappings')) {
            Schema::create('lcd_ede_mappings', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('ede_version_id')->constrained('lcd_ede_versions')->restrictOnDelete();
                $table->foreignId('regulatory_profile_id')->nullable()->constrained('lcd_regulatory_profiles')->restrictOnDelete();
                $table->string('code', 100);
                $table->string('source_entity', 120);
                $table->string('source_field', 120)->nullable();
                $table->string('target_record_type', 120);
                $table->string('target_field', 120);
                $table->string('data_type', 40);
                $table->boolean('required')->default(false);
                $table->json('transform_definition')->nullable();
                $table->json('validation_definition')->nullable();
                $table->string('default_value')->nullable();
                $table->char('mapping_hash', 64);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['ede_version_id', 'code'], 'lcd_ede_mappings_version_code_uq');
                $table->index(['ede_version_id', 'target_record_type', 'active'], 'lcd_ede_mappings_target_idx');
            });
        }

        if (! Schema::hasTable('lcd_ede_exports')) {
            Schema::create('lcd_ede_exports', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('book_id')->nullable()->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('regulatory_profile_id')->constrained('lcd_regulatory_profiles')->restrictOnDelete();
                $table->foreignId('ede_version_id')->constrained('lcd_ede_versions')->restrictOnDelete();
                $table->string('export_type', 80)->default('full');
                $table->json('scope_snapshot');
                $table->string('status', 40)->default('requested');
                $table->unsignedInteger('lock_version')->default(1);
                $table->char('deduplication_key', 64);
                $table->char('source_snapshot_hash', 64)->nullable();
                $table->json('manifest')->nullable();
                $table->char('manifest_hash', 64)->nullable();
                $table->unsignedBigInteger('record_count')->default(0);
                $table->unsignedBigInteger('warning_count')->default(0);
                $table->unsignedBigInteger('error_count')->default(0);
                $table->string('validator_status', 40)->default('not_run');
                $table->string('validator_version', 100)->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('requested_at');
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('stale_at')->nullable();
                $table->text('error_summary')->nullable();
                $table->timestamps();

                $table->unique(['school_id', 'deduplication_key'], 'lcd_ede_exports_school_dedupe_uq');
                $table->index(['school_id', 'academic_year_id', 'status'], 'lcd_ede_exports_school_year_status_idx');
                $table->index(['book_id', 'created_at'], 'lcd_ede_exports_book_date_idx');
            });
        }

        if (! Schema::hasTable('lcd_ede_export_files')) {
            Schema::create('lcd_ede_export_files', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('ede_export_id')->constrained('lcd_ede_exports')->restrictOnDelete();
                $table->string('file_type', 60);
                $table->string('file_name');
                $table->string('private_path');
                $table->string('mime_type', 160);
                $table->unsignedBigInteger('size_bytes');
                $table->char('sha256', 64);
                $table->boolean('encrypted')->default(true);
                $table->json('encryption_metadata')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique(['ede_export_id', 'file_type', 'sha256'], 'lcd_ede_export_files_export_hash_uq');
            });
        }

        if (! Schema::hasTable('lcd_ede_validation_runs')) {
            Schema::create('lcd_ede_validation_runs', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('ede_export_id')->constrained('lcd_ede_exports')->restrictOnDelete();
                $table->string('validator_name', 120);
                $table->string('validator_version', 100)->nullable();
                $table->string('validator_image_digest', 160)->nullable();
                $table->string('status', 40)->default('running');
                $table->dateTime('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->integer('exit_code')->nullable();
                $table->longText('stdout_sanitized')->nullable();
                $table->longText('stderr_sanitized')->nullable();
                $table->string('report_private_path')->nullable();
                $table->char('report_hash', 64)->nullable();
                $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['ede_export_id', 'status', 'started_at'], 'lcd_ede_validation_runs_export_idx');
            });
        }

        if (! Schema::hasTable('lcd_ede_validation_results')) {
            Schema::create('lcd_ede_validation_results', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('validation_run_id')->constrained('lcd_ede_validation_runs')->restrictOnDelete();
                $table->foreignId('ede_export_id')->constrained('lcd_ede_exports')->restrictOnDelete();
                $table->string('severity', 20);
                $table->string('code', 120);
                $table->string('record_type', 120)->nullable();
                $table->string('record_reference', 160)->nullable();
                $table->string('field', 120)->nullable();
                $table->text('message');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['validation_run_id', 'severity', 'code'], 'lcd_ede_validation_results_run_idx');
                $table->index(['ede_export_id', 'record_type'], 'lcd_ede_validation_results_export_idx');
            });
        }

        if (! Schema::hasTable('lcd_fiscalization_packages')) {
            Schema::create('lcd_fiscalization_packages', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('ede_export_id')->nullable()->constrained('lcd_ede_exports')->restrictOnDelete();
                $table->string('package_type', 80);
                $table->string('status', 40)->default('queued');
                $table->json('scope_snapshot');
                $table->json('manifest')->nullable();
                $table->char('manifest_hash', 64)->nullable();
                $table->string('private_path')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->char('sha256', 64)->nullable();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('generated_at')->nullable();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->text('revocation_reason')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'academic_year_id', 'status'], 'lcd_fiscal_packages_school_year_idx');
                $table->index(['ede_export_id', 'status'], 'lcd_fiscal_packages_export_idx');
            });
        }

        if (! Schema::hasTable('lcd_attachments')) {
            Schema::create('lcd_attachments', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->string('owner_type', 120);
                $table->unsignedBigInteger('owner_id');
                $table->string('category', 80)->default('evidence');
                $table->string('original_name');
                $table->string('safe_name');
                $table->string('detected_mime_type', 160);
                $table->string('extension', 20)->nullable();
                $table->unsignedBigInteger('size_bytes');
                $table->char('sha256', 64);
                $table->string('disk', 80)->default('private');
                $table->string('private_path');
                $table->boolean('encrypted')->default(true);
                $table->json('encryption_metadata')->nullable();
                $table->string('confidentiality_level', 40)->default('restricted');
                $table->string('malware_scan_status', 40)->default('pending');
                $table->string('malware_scanner_version', 100)->nullable();
                $table->timestamp('malware_scanned_at')->nullable();
                $table->date('retention_until')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('uploaded_at');
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();

                $table->unique(['school_id', 'owner_type', 'owner_id', 'sha256'], 'lcd_attachments_owner_hash_uq');
                $table->index(['owner_type', 'owner_id', 'category'], 'lcd_attachments_owner_category_idx');
                $table->index(['school_id', 'malware_scan_status'], 'lcd_attachments_scan_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_audit_events')) {
            Schema::create('lcd_audit_events', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->restrictOnDelete();
                $table->unsignedBigInteger('sequence_number');
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('actor_staff_id')->nullable()->constrained('staff')->nullOnDelete();
                $table->foreignId('impersonator_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role_snapshot', 160)->nullable();
                $table->text('break_glass_reason')->nullable();
                $table->string('correlation_id', 100)->nullable();
                $table->string('request_id', 100)->nullable();
                $table->string('event', 120);
                $table->string('action', 80);
                $table->string('auditable_type', 120);
                $table->unsignedBigInteger('auditable_id');
                $table->unsignedInteger('revision')->nullable();
                $table->text('reason')->nullable();
                $table->char('before_hash', 64)->nullable();
                $table->char('after_hash', 64)->nullable();
                $table->longText('encrypted_diff')->nullable();
                $table->char('previous_event_hash', 64)->nullable();
                $table->char('event_hash', 64);
                $table->text('ip_address_encrypted')->nullable();
                $table->char('user_agent_hash', 64)->nullable();
                $table->dateTime('occurred_at');
                $table->timestamp('created_at')->nullable();

                $table->unique(['school_id', 'sequence_number'], 'lcd_audit_events_school_sequence_uq');
                $table->unique(['school_id', 'event_hash'], 'lcd_audit_events_school_hash_uq');
                $table->index(['auditable_type', 'auditable_id', 'occurred_at'], 'lcd_audit_events_record_date_idx');
                $table->index(['actor_user_id', 'occurred_at'], 'lcd_audit_events_actor_date_idx');
                $table->index(['correlation_id', 'request_id'], 'lcd_audit_events_correlation_idx');
            });
        }

        if (! Schema::hasTable('lcd_report_exports')) {
            Schema::create('lcd_report_exports', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('book_id')->nullable()->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('report_type', 100);
                $table->string('format', 20)->default('pdf');
                $table->string('title');
                $table->json('filters_snapshot');
                $table->string('status', 40)->default('queued');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->boolean('draft_watermark')->default(false);
                $table->string('private_path')->nullable();
                $table->string('mime_type', 160)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->char('sha256', 64)->nullable();
                $table->char('source_snapshot_hash', 64)->nullable();
                $table->string('source_private_path')->nullable();
                $table->dateTime('requested_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('failure_code', 100)->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'report_type', 'status'], 'lcd_report_exports_school_type_idx');
                $table->index(['requested_by', 'created_at'], 'lcd_report_exports_requester_idx');
                $table->index(['status', 'created_at'], 'lcd_report_exports_status_date_idx');
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: production records must never be removed by rollback.
    }
};
