<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incident_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_incident_id')->constrained('security_incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('released_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incident_assignments');
    }
};
