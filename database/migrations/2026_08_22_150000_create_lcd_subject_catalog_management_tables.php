<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_subject_catalog_profiles')) {
            Schema::create('lcd_subject_catalog_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('schedule_subject_id')->constrained('schedule_subjects')->restrictOnDelete();
                $table->string('display_name')->nullable();
                $table->string('subject_type', 40)->default('official');
                $table->text('description')->nullable();
                $table->json('education_types')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('schedule_subject_id', 'lcd_subject_profile_subject_uq');
                $table->index(['subject_type', 'schedule_subject_id'], 'lcd_subject_profile_type_idx');
            });
        }

        if (! Schema::hasTable('lcd_subject_external_aliases')) {
            Schema::create('lcd_subject_external_aliases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('schedule_subject_id')->constrained('schedule_subjects')->restrictOnDelete();
                $table->string('source_system', 40);
                $table->string('scope_code', 40);
                $table->string('education_type', 30)->default('all');
                $table->string('external_name');
                $table->string('normalized_name');
                $table->char('mapping_key', 64);
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->unique(['school_id', 'source_system', 'mapping_key'], 'lcd_subject_alias_mapping_uq');
                $table->index(['school_id', 'source_system', 'active'], 'lcd_subject_alias_source_idx');
                $table->index(['schedule_subject_id', 'active'], 'lcd_subject_alias_subject_idx');
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: catalog profiles and confirmed import mappings are retained on rollback.
    }
};
