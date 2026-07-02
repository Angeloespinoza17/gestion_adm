<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_goods_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_type', 40);
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('recibido_en_porteria');
            $table->dateTime('moved_at');
            $table->dateTime('delivered_at')->nullable();
            $table->string('contact_name');
            $table->string('contact_rut', 20)->nullable();
            $table->string('company')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('vehicle_plate', 20)->nullable();
            $table->text('goods_detail');
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('document_type', 40)->nullable();
            $table->string('document_number', 120)->nullable();
            $table->text('observations')->nullable();
            $table->string('received_by_name')->nullable();
            $table->string('received_by_identifier', 120)->nullable();
            $table->text('delivery_observations')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'moved_at'], 'pgm_status_moved_idx');
            $table->index(['department_id', 'status'], 'pgm_department_status_idx');
            $table->index(['movement_type', 'moved_at'], 'pgm_type_moved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_goods_movements');
    }
};
