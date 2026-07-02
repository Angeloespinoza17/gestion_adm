<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prevent_fire_extinguishers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('extinguisher_type');
            $table->string('building');
            $table->string('floor')->nullable();
            $table->string('dependency_name');
            $table->date('installed_at');
            $table->date('expires_at');
            $table->string('status', 30)->default('vigente');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['building', 'floor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prevent_fire_extinguishers');
    }
};
