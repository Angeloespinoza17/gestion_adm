<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('contract_template_id')->nullable()->constrained('contract_templates')->nullOnDelete();
            $table->string('contract_type', 100)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('position_name')->nullable();
            $table->decimal('contract_hours', 6, 2)->nullable();
            $table->string('workday', 100)->nullable();
            $table->decimal('base_salary', 12, 2)->nullable();
            $table->text('allowances')->nullable();
            $table->string('place_of_signature')->nullable();
            $table->date('signature_date')->nullable();
            $table->string('status', 50)->default('borrador');
            $table->longText('rendered_content')->nullable();
            $table->string('exported_word_path')->nullable();
            $table->string('exported_pdf_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('custom_variables')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_id', 'status']);
            $table->index(['contract_type', 'start_date']);
            $table->index(['status', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
