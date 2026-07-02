<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('rut', 20)->nullable()->unique();
            $table->date('birthdate')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('general_status', 50)->default('activo');
            $table->text('observations')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relationship', 100)->nullable();
            $table->string('guardian_phone', 50)->nullable();
            $table->string('guardian_email')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['general_status', 'last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
