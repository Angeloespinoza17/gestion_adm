<?php

namespace Tests\Feature\Remuneration;

use App\Models\Accounting\AccountingJournalEntry;
use App\Models\Remuneration\RemunerationAccountingExport;
use App\Models\Remuneration\RemunerationEmployeeProfile;
use App\Models\Remuneration\RemunerationLegalParameter;
use App\Models\Remuneration\RemunerationPayroll;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\User;
use Database\Seeders\RemunerationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemunerationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_calculation_keeps_snapshot_and_distribution(): void
    {
        $this->seed(RemunerationSeeder::class);
        Sanctum::actingAs(User::query()->firstOrFail());

        $period = RemunerationPeriod::query()->where('year', 2026)->where('month', 6)->firstOrFail();
        $profile = RemunerationEmployeeProfile::query()->skip(1)->first() ?: RemunerationEmployeeProfile::query()->firstOrFail();

        $response = $this->postJson('/api/remuneraciones/payrolls/calculate', [
            'period_id' => $period->id,
            'staff_id' => $profile->staff_id,
            'payroll_type' => 'test',
        ]);

        $response->assertOk();

        $payroll = RemunerationPayroll::query()
            ->with(['lines', 'distributions'])
            ->where('period_id', $period->id)
            ->where('staff_id', $profile->staff_id)
            ->where('payroll_type', 'test')
            ->firstOrFail();

        $this->assertNotEmpty($payroll->snapshot['parameters'] ?? []);
        $this->assertNotEmpty($payroll->snapshot['contract_setting'] ?? []);
        $this->assertGreaterThan(0, $payroll->lines->count());
        $this->assertSame((int) $payroll->total_cost, (int) $payroll->distributions->sum('total_cost_amount'));
    }

    public function test_closed_period_rejects_recalculation(): void
    {
        $this->seed(RemunerationSeeder::class);
        Sanctum::actingAs(User::query()->firstOrFail());

        $period = RemunerationPeriod::query()->where('year', 2026)->where('month', 7)->firstOrFail();
        $period->forceFill(['status' => 'cerrado'])->save();
        $profile = RemunerationEmployeeProfile::query()->firstOrFail();

        $response = $this->postJson('/api/remuneraciones/payrolls/calculate', [
            'period_id' => $period->id,
            'staff_id' => $profile->staff_id,
        ]);

        $response->assertStatus(422);
    }

    public function test_seeded_accounting_export_is_balanced(): void
    {
        $this->seed(RemunerationSeeder::class);

        $export = RemunerationAccountingExport::query()->with('journalEntry.lines')->firstOrFail();
        $entry = AccountingJournalEntry::query()->with('lines')->findOrFail($export->journal_entry_id);

        $this->assertSame(
            (float) $entry->lines->sum('debit'),
            (float) $entry->lines->sum('credit')
        );
    }

    public function test_payroll_pdf_data_uses_persisted_snapshot_after_parameter_change(): void
    {
        $this->seed(RemunerationSeeder::class);
        Sanctum::actingAs(User::query()->firstOrFail());

        $payroll = RemunerationPayroll::query()->where('payroll_type', 'mensual')->firstOrFail();
        $originalAfpValue = collect($payroll->snapshot['parameters'] ?? [])->firstWhere('code', 'afp_rate_default')['value'] ?? null;

        RemunerationLegalParameter::query()
            ->where('code', 'afp_rate_default')
            ->update(['value' => 99]);

        $response = $this->getJson('/api/remuneraciones/payrolls/pdf-data?payroll_id=' . $payroll->id);

        $response->assertOk()
            ->assertJsonPath('historical_snapshot', true)
            ->assertJsonPath('data.0.id', $payroll->id);

        $exportedAfpValue = collect($response->json('data.0.snapshot.parameters'))->firstWhere('code', 'afp_rate_default')['value'] ?? null;

        $this->assertSame($originalAfpValue, $exportedAfpValue);
        $this->assertNotSame(99.0, $exportedAfpValue);
    }

    public function test_human_resources_resources_are_seeded_and_available(): void
    {
        $this->seed(RemunerationSeeder::class);
        Sanctum::actingAs(User::query()->firstOrFail());

        $this->getJson('/api/remuneraciones/catalogs')
            ->assertOk()
            ->assertJsonStructure(['statuses', 'types', 'data', 'permissions']);
        $this->getJson('/api/remuneraciones/resources/medical-leaves')->assertOk();
        $this->getJson('/api/remuneraciones/resources/document-controls')->assertOk();
        $this->getJson('/api/remuneraciones/resources/labor-certificates')->assertOk();
    }
}
