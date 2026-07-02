<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_request_id')->constrained('permission_requests')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->string('validation_status', 60)->default('pendiente');
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['permission_request_id', 'validation_status'], 'prd_request_validation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_request_documents');
    }
};
