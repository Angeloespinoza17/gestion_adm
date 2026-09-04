<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('supply_deliveries', 'recipient_staff_id')) {
            Schema::table('supply_deliveries', function (Blueprint $table): void {
                $table->foreignId('recipient_staff_id')
                    ->nullable()
                    ->after('delivered_at')
                    ->constrained('staff')
                    ->nullOnDelete();
                $table->index(['recipient_staff_id', 'delivered_at'], 'supply_deliveries_recipient_staff_date_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('supply_deliveries', 'recipient_staff_id')) {
            Schema::table('supply_deliveries', function (Blueprint $table): void {
                $table->dropForeign(['recipient_staff_id']);
                $table->dropIndex('supply_deliveries_recipient_staff_date_index');
                $table->dropColumn('recipient_staff_id');
            });
        }
    }
};
