<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prevent_epp_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('epp_type');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->string('unit')->default('unidad');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['epp_type', 'active']);
        });

        Schema::create('prevent_epp_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('epp_item_id')->constrained('prevent_epp_items')->cascadeOnDelete();
            $table->string('employee_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->date('delivered_at');
            $table->date('replacement_due_at')->nullable();
            $table->string('status', 30)->default('vigente');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'replacement_due_at']);
            $table->index('employee_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prevent_epp_deliveries');
        Schema::dropIfExists('prevent_epp_items');
    }
};
