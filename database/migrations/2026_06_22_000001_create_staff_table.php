<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('rut', 20)->unique();
            $table->date('birth_date')->nullable();
            $table->string('institutional_email')->nullable()->unique();
            $table->string('personal_email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('commune', 191)->nullable();
            $table->string('region', 191)->nullable();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
            $table->string('contract_type', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 50)->default('activo');
            $table->string('workday', 100)->nullable();
            $table->decimal('contract_hours', 6, 2)->nullable();
            $table->string('professional_title')->nullable();
            $table->string('specialty')->nullable();
            $table->string('professional_registration')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cargo_id', 'active']);
            $table->index(['status', 'contract_type']);
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
