<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_external_service_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->unsignedBigInteger('maintenance_dependency_id')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('en_curso');
            $table->dateTime('entered_at');
            $table->dateTime('exited_at')->nullable();
            $table->string('service_type');
            $table->string('company_name')->nullable();
            $table->string('contact_name');
            $table->string('contact_rut', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('vehicle_plate', 20)->nullable();
            $table->text('observations')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('maintenance_dependency_id', 'pese_dependency_fk')
                ->references('id')
                ->on('maintenance_dependencies')
                ->nullOnDelete();
            $table->index(['status', 'entered_at'], 'pse_status_entered_idx');
            $table->index(['maintenance_dependency_id', 'status'], 'pse_dep_status_idx');
            $table->index(['responsible_staff_id', 'status'], 'pse_staff_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_external_service_entries');
    }
};
