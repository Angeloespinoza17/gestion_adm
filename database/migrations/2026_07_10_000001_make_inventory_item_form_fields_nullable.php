<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->string('status')->nullable()->default(null)->change();
            $table->string('condition')->nullable()->default(null)->change();
            $table->boolean('active')->nullable()->default(true)->change();
            $table->string('item_type')->nullable()->default('asset')->change();
            $table->boolean('has_warranty')->nullable()->default(false)->change();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('inventory_categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });
        }

        $categoryId = DB::table('inventory_categories')->value('id');

        if (!$categoryId) {
            $categoryId = DB::table('inventory_categories')->insertGetId([
                'name' => 'Sin categoria',
                'slug' => 'sin-categoria-rollback',
                'code_prefix' => 'SIN',
                'description' => null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('inventory_items')->whereNull('name')->update(['name' => 'Sin nombre']);
        DB::table('inventory_items')->whereNull('category_id')->update(['category_id' => $categoryId]);
        DB::table('inventory_items')->whereNull('status')->update(['status' => 'Activo']);
        DB::table('inventory_items')->whereNull('condition')->update(['condition' => 'Bueno']);
        DB::table('inventory_items')->whereNull('active')->update(['active' => true]);
        DB::table('inventory_items')->whereNull('item_type')->update(['item_type' => 'asset']);
        DB::table('inventory_items')->whereNull('has_warranty')->update(['has_warranty' => false]);

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('name')->change();
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->string('status')->default('Activo')->change();
            $table->string('condition')->default('Bueno')->change();
            $table->boolean('active')->default(true)->change();
            $table->string('item_type')->default('asset')->change();
            $table->boolean('has_warranty')->default(false)->change();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('inventory_categories')
                    ->restrictOnDelete();
            });
        }
    }
};
