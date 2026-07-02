<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_clause_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_id')->constrained('contract_templates')->cascadeOnDelete();
            $table->foreignId('contract_clause_id')->constrained('contract_clauses')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['contract_template_id', 'contract_clause_id'], 'contract_template_clause_unique');
            $table->index(['contract_template_id', 'sort_order'], 'contract_template_clause_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_clause_template');
    }
};
