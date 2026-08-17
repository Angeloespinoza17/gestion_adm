<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_schools')) {
            Schema::create('lcd_schools', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('rbd', 20)->unique();
                $table->string('name');
                $table->string('legal_name')->nullable();
                $table->string('dependency_type', 80)->nullable();
                $table->string('region', 120)->nullable();
                $table->string('commune', 120)->nullable();
                $table->string('address')->nullable();
                $table->string('timezone', 64)->default('America/Santiago');
                $table->json('modalities')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['active', 'name'], 'lcd_schools_active_name_idx');
            });
        }

        if (! Schema::hasTable('lcd_regulatory_profiles')) {
            Schema::create('lcd_regulatory_profiles', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('code', 80);
                $table->string('name');
                $table->string('version', 50);
                $table->string('authority', 160)->nullable();
                $table->string('resolution_number', 100)->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->unsignedSmallInteger('retention_years')->default(6);
                $table->json('rules_snapshot')->nullable();
                $table->char('source_hash', 64)->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['code', 'version'], 'lcd_reg_profiles_code_version_uq');
                $table->index(['active', 'effective_from', 'effective_to'], 'lcd_reg_profiles_effective_idx');
            });
        }

        if (! Schema::hasTable('lcd_regulatory_rules')) {
            Schema::create('lcd_regulatory_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('regulatory_profile_id')->constrained('lcd_regulatory_profiles')->restrictOnDelete();
                $table->string('code', 100);
                $table->string('category', 80);
                $table->string('name');
                $table->json('rule_definition');
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['regulatory_profile_id', 'code'], 'lcd_reg_rules_profile_code_uq');
                $table->index(['category', 'active'], 'lcd_reg_rules_category_active_idx');
            });
        }

        if (! Schema::hasTable('lcd_school_academic_years')) {
            Schema::create('lcd_school_academic_years', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('regulatory_profile_id')->nullable()->constrained('lcd_regulatory_profiles')->restrictOnDelete();
                $table->string('rbd_snapshot', 20);
                $table->unsignedSmallInteger('year_snapshot');
                $table->string('timezone_snapshot', 64)->default('America/Santiago');
                $table->date('opened_on')->nullable();
                $table->date('closed_on')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'academic_year_id'], 'lcd_school_years_school_year_uq');
                $table->index(['school_id', 'active'], 'lcd_school_years_active_idx');
            });
        }

        if (! Schema::hasTable('lcd_school_users')) {
            Schema::create('lcd_school_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->string('role_snapshot', 120)->nullable();
                $table->json('permission_scope')->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'user_id'], 'lcd_school_users_school_user_uq');
                $table->index(['school_id', 'active'], 'lcd_school_users_active_idx');
            });
        }

        if (! Schema::hasTable('lcd_feature_flags')) {
            Schema::create('lcd_feature_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->nullable()->constrained('lcd_schools')->restrictOnDelete();
                $table->string('scope_key', 80)->default('global');
                $table->string('code', 100);
                $table->boolean('enabled')->default(false);
                $table->json('configuration')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['scope_key', 'code'], 'lcd_feature_flags_scope_code_uq');
                $table->index(['school_id', 'enabled'], 'lcd_feature_flags_school_enabled_idx');
            });
        }

        if (! Schema::hasTable('lcd_settings')) {
            Schema::create('lcd_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->nullable()->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->restrictOnDelete();
                $table->string('scope_key', 120)->default('global');
                $table->string('key', 120);
                $table->json('value');
                $table->boolean('is_encrypted')->default(false);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['scope_key', 'key'], 'lcd_settings_scope_key_uq');
                $table->index(['school_id', 'academic_year_id'], 'lcd_settings_school_year_idx');
            });
        }

        if (! Schema::hasTable('lcd_normative_sources')) {
            Schema::create('lcd_normative_sources', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('regulatory_profile_id')->nullable()->constrained('lcd_regulatory_profiles')->restrictOnDelete();
                $table->string('title');
                $table->string('authority', 160)->nullable();
                $table->string('document_number', 100)->nullable();
                $table->date('published_on')->nullable();
                $table->text('source_url')->nullable();
                $table->timestamp('consulted_at')->nullable();
                $table->char('sha256', 64)->nullable();
                $table->string('private_path')->nullable();
                $table->string('status', 30)->default('verified');
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['regulatory_profile_id', 'status'], 'lcd_norm_sources_profile_status_idx');
                $table->index('sha256', 'lcd_norm_sources_sha_idx');
            });
        }

        if (! Schema::hasTable('lcd_reference_catalogs')) {
            Schema::create('lcd_reference_catalogs', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('normative_source_id')->nullable()->constrained('lcd_normative_sources')->restrictOnDelete();
                $table->string('code', 100);
                $table->string('name');
                $table->string('version', 50);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['code', 'version'], 'lcd_ref_catalogs_code_version_uq');
            });
        }

        if (! Schema::hasTable('lcd_reference_values')) {
            Schema::create('lcd_reference_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reference_catalog_id')->constrained('lcd_reference_catalogs')->restrictOnDelete();
                $table->string('code', 100);
                $table->string('label');
                $table->json('metadata')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['reference_catalog_id', 'code'], 'lcd_ref_values_catalog_code_uq');
                $table->index(['reference_catalog_id', 'active'], 'lcd_ref_values_catalog_active_idx');
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: production records must never be removed by rollback.
    }
};
