<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingJournalEntry;
use App\Models\User;
use Database\Seeders\AccountingModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_seeder_generates_balanced_journal_entries(): void
    {
        $this->seed(AccountingModuleSeeder::class);

        $entries = AccountingJournalEntry::query()->with('lines')->get();

        $this->assertNotEmpty($entries);

        foreach ($entries as $entry) {
            $debit = round((float) $entry->lines->sum('debit'), 2);
            $credit = round((float) $entry->lines->sum('credit'), 2);

            $this->assertSame(
                $debit,
                $credit,
                sprintf('El asiento %s no cuadra.', $entry->entry_number)
            );
        }
    }

    public function test_super_admin_can_open_accounting_dashboard_api(): void
    {
        $this->seed(AccountingModuleSeeder::class);

        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/contabilidad/dashboard');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'metrics',
                'alerts',
                'summaries',
                'recent',
            ]);
    }
}
