<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('contract_signer_id')->nullable()->constrained('contract_signers')->nullOnDelete();
            $table->string('name');
            $table->string('rut', 20)->nullable();
            $table->string('position')->nullable();
            $table->string('signer_type', 100)->nullable();
            $table->string('signature_image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('use_signature_image')->default(true);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signatures');
    }
};
