<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prevent_accidents', function (Blueprint $table) {
            $table->id();
            $table->dateTime('occurred_at');
            $table->string('accident_type', 30);
            $table->string('involved_person_name');
            $table->string('involved_person_identifier')->nullable();
            $table->string('location');
            $table->text('description');
            $table->text('injuries')->nullable();
            $table->text('measures_taken')->nullable();
            $table->text('referrals')->nullable();
            $table->string('case_status', 30)->default('abierto');
            $table->string('responsible_name')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['accident_type', 'case_status']);
            $table->index('occurred_at');
        });

        Schema::create('prevent_accident_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accident_id')->constrained('prevent_accidents')->cascadeOnDelete();
            $table->dateTime('followed_at');
            $table->string('status', 30);
            $table->text('notes');
            $table->text('next_actions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['accident_id', 'followed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prevent_accident_follow_ups');
        Schema::dropIfExists('prevent_accidents');
    }
};
