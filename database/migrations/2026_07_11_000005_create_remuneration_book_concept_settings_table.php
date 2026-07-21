<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remuneration_book_concept_settings', function (Blueprint $table) {
            $table->id();
            $table->string('concept_key', 40)->unique();
            $table->string('code', 80)->nullable()->index();
            $table->string('name');
            $table->string('label');
            $table->string('nature', 40)->index();
            $table->string('group', 120)->nullable()->index();
            $table->boolean('is_union_income')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['nature', 'is_union_income'], 'rem_book_concept_settings_nature_union_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remuneration_book_concept_settings');
    }
};
