<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_dependency_id')->nullable()->constrained()->nullOnDelete();
            $table->date('reported_at')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('priority')->default('Media');
            $table->string('status')->default('Sin comenzar');
            $table->date('due_date')->nullable();
            $table->text('description');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_work_orders');
    }
};
