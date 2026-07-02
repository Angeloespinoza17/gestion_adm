<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prevent_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);
            $table->string('title');
            $table->string('document_group')->nullable();
            $table->string('version_number', 30);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status', 30)->default('vigente');
            $table->string('responsible_name')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type', 'status']);
            $table->index(['valid_until', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prevent_documents');
    }
};
