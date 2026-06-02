<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->string('qr_code')->nullable();
            $table->string('barcode')->nullable();

            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('inventory_subcategories')->nullOnDelete();

            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();

            $table->date('purchase_date')->nullable();
            $table->unsignedBigInteger('purchase_value')->nullable();
            $table->unsignedTinyInteger('useful_life_years')->nullable();

            $table->string('status')->default('Activo');
            $table->string('condition')->default('Bueno');

            // Integración con dependencias del módulo de mantención.
            $table->foreignId('dependency_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->string('image_path')->nullable();
            $table->boolean('active')->default(true);

            // Diferencia entre activo único e insumo con stock.
            $table->string('item_type')->default('asset'); // asset | consumable
            $table->decimal('stock_quantity', 12, 2)->nullable();
            $table->decimal('minimum_stock', 12, 2)->nullable();
            $table->string('unit_of_measure', 50)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'item_type']);
            $table->index(['category_id', 'subcategory_id']);
            $table->index(['dependency_id', 'responsible_user_id']);
            $table->index('status');
            $table->index('condition');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};

