<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('allows_with_pay')->default(true);
            $table->boolean('allows_without_pay')->default(true);
            $table->boolean('allows_hourly')->default(false);
            $table->boolean('allows_half_day')->default(false);
            $table->boolean('requires_manager_approval')->default(true);
            $table->boolean('requires_direction_approval')->default(false);
            $table->boolean('requires_hr_approval')->default(false);
            $table->decimal('max_days', 6, 2)->nullable();
            $table->unsignedInteger('minimum_notice_days')->nullable();
            $table->boolean('allows_retroactive')->default(false);
            $table->boolean('affects_salary')->default(false);
            $table->boolean('affects_attendance')->default(true);
            $table->boolean('requires_replacement')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_types');
    }
};
