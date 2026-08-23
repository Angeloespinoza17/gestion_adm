<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_budget_execution_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('school_name')->nullable();
            $table->string('original_filename');
            $table->char('sha256', 64);
            $table->unsignedTinyInteger('reported_through_month')->nullable();
            $table->unsignedInteger('line_count')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at');
            $table->timestamps();
        });

        Schema::create('accounting_budget_execution_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('accounting_budget_execution_imports')->cascadeOnDelete();
            $table->string('subsidy_code', 40)->index();
            $table->string('subsidy_name', 100);
            $table->string('flow_type', 20)->index();
            $table->string('category')->nullable()->index();
            $table->string('account_name');
            $table->decimal('annual_budget', 18, 2)->nullable();
            foreach (['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'] as $month) {
                $table->decimal($month, 18, 2)->nullable();
            }
            $table->string('source_sheet', 80);
            $table->unsignedSmallInteger('source_row');
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->index(['import_id', 'flow_type', 'subsidy_code'], 'acc_budget_exec_lookup_idx');
        });
    }

    public function down(): void
    {
        // Forward-only migration: imported accounting records are never removed.
    }
};
