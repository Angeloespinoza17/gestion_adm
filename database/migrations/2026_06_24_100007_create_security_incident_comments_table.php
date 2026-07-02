<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incident_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_incident_id')->constrained('security_incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('security_incident_statuses')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment');
            $table->dateTime('responded_at')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incident_comments');
    }
};
