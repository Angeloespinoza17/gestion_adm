<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_storerooms', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'name']);
        });

        Schema::table('supply_items', function (Blueprint $table): void {
            $table->foreignId('storeroom_id')
                ->nullable()
                ->after('section')
                ->constrained('supply_storerooms')
                ->nullOnDelete();
            $table->index(['section', 'storeroom_id']);
        });

        Schema::table('supply_receipts', function (Blueprint $table): void {
            $table->foreignId('storeroom_id')
                ->nullable()
                ->after('section')
                ->constrained('supply_storerooms')
                ->nullOnDelete();
            $table->index(['section', 'storeroom_id', 'purchased_at'], 'supply_receipts_storeroom_date_idx');
        });

        Schema::table('supply_deliveries', function (Blueprint $table): void {
            $table->foreignId('storeroom_id')
                ->nullable()
                ->after('section')
                ->constrained('supply_storerooms')
                ->nullOnDelete();
            $table->index(['section', 'storeroom_id', 'delivered_at'], 'supply_deliveries_storeroom_date_idx');
        });

        DB::table('supply_storerooms')->insertOrIgnore([
            'code' => 'PANOL-CENTRAL',
            'name' => 'Pañol central',
            'description' => 'Bodega principal de herramientas y artículos de mantenimiento.',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('supply_deliveries', function (Blueprint $table): void {
            $table->dropIndex('supply_deliveries_storeroom_date_idx');
            $table->dropConstrainedForeignId('storeroom_id');
        });
        Schema::table('supply_receipts', function (Blueprint $table): void {
            $table->dropIndex('supply_receipts_storeroom_date_idx');
            $table->dropConstrainedForeignId('storeroom_id');
        });
        Schema::table('supply_items', function (Blueprint $table): void {
            $table->dropIndex(['section', 'storeroom_id']);
            $table->dropConstrainedForeignId('storeroom_id');
        });
        Schema::dropIfExists('supply_storerooms');
    }
};
