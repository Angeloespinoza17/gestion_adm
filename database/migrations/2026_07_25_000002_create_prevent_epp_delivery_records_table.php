<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prevent_epp_delivery_records', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable()->unique();
            $table->string('form_code', 30)->default('FO-PREV-03');
            $table->string('form_revision', 10)->default('01');
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('employee_name_snapshot', 160);
            $table->string('employee_rut_snapshot', 30)->nullable();
            $table->string('employee_position_snapshot', 160)->nullable();
            $table->date('delivered_at');
            $table->boolean('received_conformity')->default(false);
            $table->timestamp('received_conformity_at')->nullable();
            $table->string('delivered_by_name', 160)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['delivered_at', 'staff_id'], 'epp_records_date_staff_idx');
            $table->index(['received_conformity', 'delivered_at'], 'epp_records_conformity_date_idx');
        });

        Schema::table('prevent_epp_deliveries', function (Blueprint $table) {
            $table->foreignId('delivery_record_id')
                ->nullable()
                ->after('id')
                ->constrained('prevent_epp_delivery_records')
                ->cascadeOnDelete();
            $table->string('epp_name_snapshot', 160)->nullable()->after('epp_item_id');
            $table->string('unit_snapshot', 50)->nullable()->after('epp_name_snapshot');

            $table->index(['delivery_record_id', 'epp_item_id'], 'epp_deliveries_record_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('prevent_epp_deliveries', function (Blueprint $table) {
            $table->dropIndex('epp_deliveries_record_item_idx');
            $table->dropConstrainedForeignId('delivery_record_id');
            $table->dropColumn(['epp_name_snapshot', 'unit_snapshot']);
        });

        Schema::dropIfExists('prevent_epp_delivery_records');
    }
};
