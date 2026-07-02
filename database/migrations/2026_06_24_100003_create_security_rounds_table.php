<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_shift_id')->constrained('security_shifts')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('round_number');
            $table->dateTime('recorded_at');
            $table->string('overall_status', 30)->default('sin_novedad');
            $table->text('observations')->nullable();
            $table->string('nochero_confirmation_name')->nullable();
            $table->longText('signature_data')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy', 8, 2)->nullable();
            $table->string('act_number')->unique();
            $table->dateTime('act_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['security_shift_id', 'round_number']);
            $table->index(['recorded_at', 'overall_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_rounds');
    }
};
