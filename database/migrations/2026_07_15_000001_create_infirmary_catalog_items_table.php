<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infirmary_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 80);
            $table->string('code', 120);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_key', 'code'], 'inf_catalog_group_code_unique');
            $table->index(['group_key', 'active', 'sort_order'], 'inf_catalog_group_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infirmary_catalog_items');
    }
};
