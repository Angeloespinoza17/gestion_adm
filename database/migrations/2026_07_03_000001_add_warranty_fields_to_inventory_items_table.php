<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->boolean('has_warranty')->default(false);
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->date('warranty_expires_at')->nullable();

            $table->index(['has_warranty', 'warranty_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['has_warranty', 'warranty_expires_at']);
            $table->dropColumn([
                'has_warranty',
                'warranty_months',
                'warranty_expires_at',
            ]);
        });
    }
};
