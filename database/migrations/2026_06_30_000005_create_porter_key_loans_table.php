<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_key_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('porter_key_id')->constrained('porter_keys')->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('maintenance_dependency_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_to_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('prestada');
            $table->dateTime('checked_out_at');
            $table->dateTime('expected_return_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->string('requester_name');
            $table->string('requester_rut', 20)->nullable();
            $table->string('purpose')->nullable();
            $table->text('observations')->nullable();
            $table->text('return_observations')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'checked_out_at'], 'pkl_status_out_idx');
            $table->index(['porter_key_id', 'status'], 'pkl_key_status_idx');
            $table->index(['staff_id', 'status'], 'pkl_staff_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_key_loans');
    }
};
