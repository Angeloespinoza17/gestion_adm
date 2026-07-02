<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contract_type', 100)->nullable();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->longText('body')->nullable();
            $table->json('available_variables')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['active', 'contract_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
