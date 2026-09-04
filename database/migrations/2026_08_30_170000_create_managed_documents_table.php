<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('category', 80);
            $table->unsignedSmallInteger('year');
            $table->string('version', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('file_size');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_public', 'is_active', 'year'], 'managed_documents_public_lookup_idx');
            $table->index(['year', 'category'], 'managed_documents_year_category_idx');
            $table->index(['is_active', 'created_at'], 'managed_documents_active_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_documents');
    }
};
