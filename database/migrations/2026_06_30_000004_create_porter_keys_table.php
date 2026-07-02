<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_dependency_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->text('observations')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'name'], 'pk_active_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_keys');
    }
};
