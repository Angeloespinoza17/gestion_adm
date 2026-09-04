<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->string('title');
            $table->string('destination')->nullable();
            $table->date('needed_by')->nullable();
            $table->string('status', 40)->default('submitted');
            $table->text('notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('supply_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_request_id')->constrained('supply_requests')->cascadeOnDelete();
            $table->foreignId('supply_item_id')->nullable()->constrained('supply_items')->nullOnDelete();
            $table->string('item_name_snapshot');
            $table->string('description_snapshot')->nullable();
            $table->string('unit_snapshot', 50);
            $table->decimal('requested_quantity', 12, 2);
            $table->decimal('final_quantity', 12, 2);
            $table->string('reference_photo_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['supply_request_id', 'sort_order']);
            $table->index(['supply_item_id', 'supply_request_id']);
        });

        Schema::create('supply_request_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_request_id')->constrained('supply_requests')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supply_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_request_status_logs');
        Schema::dropIfExists('supply_request_items');
        Schema::dropIfExists('supply_requests');
    }
};
