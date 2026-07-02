<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('security_incident_id')->nullable()->constrained('security_incidents')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('priority', 20)->default('media');
            $table->string('action_url')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('sent_via_mail_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_notifications');
    }
};
