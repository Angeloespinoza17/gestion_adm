<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->unique()->constrained('inventory_items')->cascadeOnDelete();
            $table->string('section', 30); // cleaning | heating
            $table->string('supply_type', 60);
            $table->string('reference_photo_path')->nullable();
            $table->timestamps();

            $table->index(['section', 'supply_type']);
        });

        Schema::create('supply_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->string('section', 30);
            $table->date('purchased_at');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('document_type', 40)->nullable();
            $table->string('document_number', 100)->nullable();
            $table->unsignedBigInteger('total_amount')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['section', 'purchased_at']);
            $table->index(['supplier_id', 'purchased_at']);
        });

        Schema::create('supply_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_receipt_id')->constrained('supply_receipts')->cascadeOnDelete();
            $table->foreignId('supply_item_id')->constrained('supply_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->string('unit_snapshot', 50);
            $table->decimal('previous_stock', 12, 2);
            $table->decimal('new_stock', 12, 2);
            $table->timestamps();

            $table->index(['supply_receipt_id', 'supply_item_id']);
        });

        Schema::create('supply_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->string('section', 30);
            $table->date('delivered_at');
            $table->string('recipient_name');
            $table->string('recipient_rut', 20)->nullable();
            $table->string('recipient_role')->nullable();
            $table->string('destination')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['section', 'delivered_at']);
            $table->index(['recipient_name', 'delivered_at']);
        });

        Schema::create('supply_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_delivery_id')->constrained('supply_deliveries')->cascadeOnDelete();
            $table->foreignId('supply_item_id')->constrained('supply_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('item_name_snapshot');
            $table->string('unit_snapshot', 50);
            $table->decimal('previous_stock', 12, 2);
            $table->decimal('new_stock', 12, 2);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['supply_delivery_id', 'supply_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_delivery_items');
        Schema::dropIfExists('supply_deliveries');
        Schema::dropIfExists('supply_receipt_items');
        Schema::dropIfExists('supply_receipts');
        Schema::dropIfExists('supply_items');
    }
};
