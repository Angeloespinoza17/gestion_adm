<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_dependency_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_dependency_id')
                ->constrained('maintenance_dependencies')
                ->cascadeOnDelete();
            $table->dateTime('audited_at');
            $table->unsignedInteger('expected_items_count')->default(0);
            $table->unsignedInteger('found_items_count')->default(0);
            $table->unsignedInteger('missing_items_count')->default(0);
            $table->unsignedInteger('critical_items_count')->default(0);
            $table->unsignedInteger('low_stock_items_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('audited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['maintenance_dependency_id', 'audited_at'], 'inv_dep_audits_dep_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_dependency_audits');
    }
};
