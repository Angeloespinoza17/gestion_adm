<?php

use App\Services\Accounting\AccountingSubsidyService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('accounting_subsidy_settlement_lines')
            || ! Schema::hasTable('accounting_subsidy_allocations')
            || ! Schema::hasTable('accounting_subsidy_imports')
        ) {
            return;
        }

        app(AccountingSubsidyService::class)->backfillPieDetails();
    }

    public function down(): void
    {
        // PIE allocations are valid imported source data and are intentionally kept.
    }
};
