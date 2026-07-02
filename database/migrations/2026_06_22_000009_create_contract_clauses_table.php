<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_clauses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('clause_type', 100)->nullable();
            $table->longText('content');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['active', 'sort_order']);
            $table->index('clause_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_clauses');
    }
};
