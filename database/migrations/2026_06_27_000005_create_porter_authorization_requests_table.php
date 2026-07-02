<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_authorization_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('authorizable', 'par_authorizable_idx');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('pendiente');
            $table->string('required_permission_slug')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at'], 'par_status_requested_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_authorization_requests');
    }
};
