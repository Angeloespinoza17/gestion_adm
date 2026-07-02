<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_signers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rut', 20)->nullable();
            $table->string('position')->nullable();
            $table->string('signer_type', 100);
            $table->string('signature_image_path')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['active', 'sort_order']);
            $table->index('signer_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signers');
    }
};
