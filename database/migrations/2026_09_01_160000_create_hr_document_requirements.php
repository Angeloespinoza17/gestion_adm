<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_delivery')->default(true);
            $table->boolean('requires_signature')->default(false);
            $table->string('validity_mode', 30)->default('none')->index();
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->unsignedSmallInteger('alert_days')->default(30);
            $table->boolean('is_required')->default(true)->index();
            $table->boolean('active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'sort_order'], 'hr_doc_req_active_sort_idx');
        });

        Schema::table('hr_document_controls', function (Blueprint $table) {
            $table->foreignId('document_requirement_id')
                ->nullable()
                ->constrained('hr_document_requirements')
                ->nullOnDelete();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable()->index();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_disk', 40)->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->unique(
                ['staff_id', 'document_requirement_id'],
                'hr_docs_staff_requirement_unique'
            );
            $table->index(
                ['document_requirement_id', 'expires_at'],
                'hr_docs_requirement_expiry_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('hr_document_controls', function (Blueprint $table) {
            $table->dropUnique('hr_docs_staff_requirement_unique');
            $table->dropIndex('hr_docs_requirement_expiry_idx');
            $table->dropConstrainedForeignId('document_requirement_id');
            $table->dropConstrainedForeignId('delivered_by');
            $table->dropConstrainedForeignId('signed_by');
            $table->dropColumn([
                'delivered_at',
                'signed_at',
                'file_disk',
                'original_name',
                'mime_type',
                'file_size',
            ]);
        });

        Schema::dropIfExists('hr_document_requirements');
    }
};
