<?php

namespace Tests\Feature\Remuneration;

use App\Models\Accounting\AccountingJournalEntry;
use App\Models\Cargo;
use App\Models\Department;
use App\Models\Remuneration\RemunerationAccountingExport;
use App\Models\Remuneration\RemunerationBookAlertRule;
use App\Models\Remuneration\RemunerationBookConceptSetting;
use App\Models\Remuneration\RemunerationBookImport;
use App\Models\Remuneration\RemunerationBookImportRow;
use App\Models\Remuneration\RemunerationEmployeeProfile;
use App\Models\Remuneration\RemunerationLegalParameter;
use App\Models\Remuneration\RemunerationPayroll;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RemunerationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

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
        $this->getJson('/api/remuneraciones/resources/departments')->assertOk();
        $this->getJson('/api/remuneraciones/resources/functions')->assertOk();

        $this->assertSame(1, Department::query()->where('slug', 'equipo-directivo')->count());
        $this->assertSame(1, Cargo::query()->where('slug', 'docente')->count());
    }

    public function test_remuneration_book_import_preview_and_commit_creates_imported_payrolls(): void
    {
        $this->seed(RemunerationSeeder::class);
        Sanctum::actingAs(User::query()->firstOrFail());

        $staff = Staff::query()->whereNotNull('rut')->firstOrFail();
        $path = $this->makeRemunerationBookFixture($staff);

        $preview = $this->post('/api/remuneraciones/imports/preview', [
            'file' => new UploadedFile($path, 'libro_remuneraciones_abril.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $preview->assertOk()
            ->assertJsonPath('period.year', 2026)
            ->assertJsonPath('period.month', 4)
            ->assertJsonPath('summary.row_count', 2)
            ->assertJsonPath('summary.matched_count', 1)
            ->assertJsonPath('summary.unmatched_count', 1)
            ->assertJsonPath('summary.error_count', 0)
            ->assertJsonPath('summary.warning_count', 1)
            ->assertJsonPath('summary.net_total', 1020000);

        $response = $this->post('/api/remuneraciones/imports', [
            'file' => new UploadedFile($path, 'libro_remuneraciones_abril.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.row_count', 2)
            ->assertJsonPath('data.matched_count', 1)
            ->assertJsonPath('data.unmatched_count', 1);

        $period = RemunerationPeriod::query()->where('year', 2026)->where('month', 4)->firstOrFail();
        $import = RemunerationBookImport::query()->where('period_id', $period->id)->firstOrFail();
        $this->assertSame(2, RemunerationBookImportRow::query()->where('book_import_id', $import->id)->count());
        $unmatchedRow = RemunerationBookImportRow::query()
            ->where('book_import_id', $import->id)
            ->whereNull('staff_id')
            ->firstOrFail();
        $this->assertSame('99.999.999-9', $unmatchedRow->rut);
        $this->assertSame(170000, $unmatchedRow->net_amount);
        $this->assertSame(200000, $unmatchedRow->raw_totals['total_imponible_tope']);
        $this->assertCount(14, $unmatchedRow->raw_earnings_columns);
        $this->assertCount(16, $unmatchedRow->raw_deductions_columns);
        $this->assertSame('Sin ficha', collect($unmatchedRow->raw_earnings_columns)->firstWhere('header', 'Observacion')['value']);
        $this->assertSame('Descuento observado', collect($unmatchedRow->raw_deductions_columns)->firstWhere('header', 'Observacion descuento')['value']);

        $payroll = RemunerationPayroll::query()
            ->with(['lines', 'distributions'])
            ->where('period_id', $period->id)
            ->where('staff_id', $staff->id)
            ->firstOrFail();
        $this->assertSame(1, RemunerationPayroll::query()->where('period_id', $period->id)->where('source', 'imported')->count());

        $this->assertSame($import->id, $payroll->book_import_id);
        $this->assertSame('imported', $payroll->source);
        $this->assertSame(1050000, $payroll->gross_total);
        $this->assertSame(200000, $payroll->total_deductions);
        $this->assertSame(15000, $payroll->employer_contributions);
        $this->assertSame(850000, $payroll->net_amount);
        $this->assertSame(1065000, $payroll->total_cost);
        $this->assertSame('Texto libre', collect($payroll->snapshot['book_columns']['earnings'])->firstWhere('header', 'Observacion')['value']);
        $this->assertGreaterThanOrEqual(5, $payroll->lines->count());
        $this->assertSame(1065000, (int) $payroll->distributions->sum('total_cost_amount'));

        $analytics = $this->getJson('/api/remuneraciones/book-analytics?period_id=' . $period->id);

        $analytics->assertOk()
            ->assertJsonPath('current_import.id', $import->id)
            ->assertJsonPath('metrics.workers', 2)
            ->assertJsonPath('metrics.paid_workers', 2)
            ->assertJsonPath('metrics.gross_total', 1260000)
            ->assertJsonPath('metrics.net_total', 1020000)
            ->assertJsonPath('metrics.total_deductions', 240000)
            ->assertJsonPath('metrics.average_net_paid', 510000)
            ->assertJsonPath('alerts.reconciliation_rate', 100)
            ->assertJsonPath('coverage.rows', 2);

        $this->assertNotEmpty($analytics->json('composition.earnings'));
        $this->assertNotEmpty($analytics->json('composition.deductions'));
        $this->assertNotEmpty($analytics->json('alerts.definitions'));

        $alertRule = $this->postJson('/api/remuneraciones/book-alert-rules', [
            'name' => 'Carga mayor a 40 horas',
            'description' => 'Controla cargas horarias superiores a 40 horas en el libro importado.',
            'severity' => 'informativa',
            'metric' => 'weekly_hours',
            'operator' => 'gt',
            'threshold_value' => 40,
        ]);

        $alertRule->assertCreated()
            ->assertJsonPath('data.name', 'Carga mayor a 40 horas')
            ->assertJsonPath('data.enabled', true);

        $this->assertTrue(RemunerationBookAlertRule::query()->where('name', 'Carga mayor a 40 horas')->exists());

        $alertRules = $this->getJson('/api/remuneraciones/book-alert-rules');
        $alertRules->assertOk()
            ->assertJsonPath('data.0.name', 'Carga mayor a 40 horas');

        $analyticsWithCustomAlert = $this->getJson('/api/remuneraciones/book-analytics?period_id=' . $period->id);
        $analyticsWithCustomAlert->assertOk()
            ->assertJsonPath('alerts.rules.0.name', 'Carga mayor a 40 horas');

        $customAlert = collect($analyticsWithCustomAlert->json('alerts.items'))->firstWhere('type', 'Carga mayor a 40 horas');
        $this->assertNotNull($customAlert);
        $this->assertFalse($customAlert['system']);
        $this->assertSame('Carga horaria', $customAlert['metric_label']);
        $this->assertStringContainsString('Controla cargas horarias', $customAlert['explanation']);

        $conceptKey = collect($analytics->json('concept_catalog'))->firstWhere('code', '0999')['key'] ?? null;
        $this->assertNotNull($conceptKey);
        $this->assertSame(1200000, collect($analytics->json('concept_catalog'))->firstWhere('code', '0999')['monthly_amount'] ?? null);

        $conceptAnalytics = $this->getJson('/api/remuneraciones/book-analytics?period_id=' . $period->id . '&concept_key=' . $conceptKey);

        $conceptAnalytics->assertOk()
            ->assertJsonPath('concept_drilldown.selected.code', '0999')
            ->assertJsonPath('concept_drilldown.selected.nature', 'Haber')
            ->assertJsonPath('concept_drilldown.metrics.total_amount', 1200000)
            ->assertJsonPath('concept_drilldown.metrics.monthly_amount', 1200000)
            ->assertJsonPath('concept_drilldown.metrics.workers', 2)
            ->assertJsonPath('concept_drilldown.metrics.average_amount', 600000)
            ->assertJsonPath('concept_drilldown.metrics.monthly_average_amount', 600000)
            ->assertJsonPath('concept_drilldown.detail_rows.0.monthly_amount', 1000000);

        $setting = $this->patchJson('/api/remuneraciones/book-concept-settings/' . $conceptKey, [
            'code' => '0999',
            'name' => 'Sueldo base',
            'label' => '(0999) Sueldo base',
            'nature' => 'Haber',
            'group' => 'Haber',
            'is_union_income' => true,
        ]);

        $setting->assertOk()
            ->assertJsonPath('data.concept_key', $conceptKey)
            ->assertJsonPath('data.is_union_income', true);

        $this->assertTrue(RemunerationBookConceptSetting::query()->where('concept_key', $conceptKey)->value('is_union_income'));

        $unionAnalytics = $this->getJson('/api/remuneraciones/book-analytics?period_id=' . $period->id);

        $unionAnalytics->assertOk()
            ->assertJsonPath('union_earnings.metrics.total_amount', 1200000)
            ->assertJsonPath('union_earnings.metrics.workers', 2)
            ->assertJsonPath('union_earnings.metrics.average_amount', 600000)
            ->assertJsonPath('union_earnings.metrics.monthly_average_amount', 1200000)
            ->assertJsonPath('union_earnings.metrics.concept_count', 1)
            ->assertJsonPath('union_earnings.by_concept.0.code', '0999');

        $this->assertTrue((bool) collect($unionAnalytics->json('concept_catalog'))->firstWhere('code', '0999')['is_union_income']);
    }

    private function makeRemunerationBookFixture(Staff $staff): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rem_book_') . '.xlsx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Worksheet" sheetId="1" r:id="rId1"/>
    <sheet name="Worksheet 1" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
</Relationships>');

        $base = [
            'A1' => 'Empresa:',
            'B1' => 'Fundacion Demo',
            'A2' => 'RUT:',
            'B2' => '65.000.000-0',
            'A3' => 'Institución:',
            'B3' => 'Colegio Demo',
            'A4' => 'RBD:',
            'B4' => '1-9',
            'A5' => 'Periodo:',
            'B5' => '2026-04-01',
            'A6' => 'Libro de Remuneraciones',
        ];

        $earnings = array_merge($base, [
            'A8' => 'Nº',
            'B8' => 'RUT',
            'C8' => 'Empleado',
            'D8' => 'Tipo Funcionario',
            'E8' => 'DT',
            'F8' => 'Carga Horaria',
            'G8' => "(0999)\nsueldo base",
            'H8' => 'Total Imponible Tope',
            'I8' => 'Total Imponible',
            'J8' => "(1070)\ncolacion",
            'K8' => 'Total No Imponible',
            'L8' => 'Tributable',
            'M8' => 'Total Haberes',
            'N8' => 'Observacion',
            'A9' => 1,
            'B9' => $staff->rut,
            'C9' => $staff->full_name,
            'D9' => 'Docente de Aula',
            'E9' => 30,
            'F9' => 44,
            'G9' => 1000000,
            'H9' => 1000000,
            'I9' => 1000000,
            'J9' => 50000,
            'K9' => 50000,
            'L9' => 1000000,
            'M9' => 1050000,
            'N9' => 'Texto libre',
            'A10' => 2,
            'B10' => '99.999.999-9',
            'C10' => 'SIN MATCH',
            'D10' => 'Asistente',
            'E10' => 30,
            'F10' => 44,
            'G10' => 200000,
            'H10' => 200000,
            'I10' => 200000,
            'J10' => 10000,
            'K10' => 10000,
            'L10' => 200000,
            'M10' => 210000,
            'N10' => 'Sin ficha',
        ]);

        $deductions = array_merge($base, [
            'A8' => 'Nº',
            'B8' => 'RUT',
            'C8' => 'Empleado',
            'D8' => 'Tipo Funcionario',
            'E8' => 'DT',
            'F8' => 'Carga Horaria',
            'G8' => "(2000)\nprevision",
            'H8' => "(2001)\nsalud",
            'I8' => 'Tot. D. Leg.',
            'J8' => "(2011)\nanticipos",
            'K8' => 'Otros Dctos.',
            'L8' => 'Tot. Desc.',
            'M8' => "(9976)\nafp/cap. indiv",
            'N8' => "(9999)\nseguro accident",
            'O8' => 'Líquido',
            'P8' => 'Observacion descuento',
            'A9' => 1,
            'B9' => $staff->rut,
            'C9' => $staff->full_name,
            'D9' => 'Docente de Aula',
            'E9' => 30,
            'F9' => 44,
            'G9' => 100000,
            'H9' => 70000,
            'I9' => 170000,
            'J9' => 30000,
            'K9' => 30000,
            'L9' => 200000,
            'M9' => 10000,
            'N9' => 5000,
            'O9' => 850000,
            'P9' => 'Sin observacion',
            'A10' => 2,
            'B10' => '99.999.999-9',
            'C10' => 'SIN MATCH',
            'D10' => 'Asistente',
            'E10' => 30,
            'F10' => 44,
            'G10' => 20000,
            'H10' => 14000,
            'I10' => 34000,
            'J10' => 6000,
            'K10' => 6000,
            'L10' => 40000,
            'M10' => 2000,
            'N10' => 1000,
            'O10' => 170000,
            'P10' => 'Descuento observado',
        ]);

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($earnings));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->sheetXml($deductions));
        $zip->close();

        return $path;
    }

    /**
     * @param  array<string, string|int|float>  $cells
     */
    private function sheetXml(array $cells): string
    {
        $rows = [];
        foreach ($cells as $coordinate => $value) {
            preg_match('/\d+/', $coordinate, $matches);
            $row = (int) $matches[0];
            $rows[$row][] = $this->cellXml($coordinate, $value);
        }

        ksort($rows);
        $xmlRows = '';
        foreach ($rows as $rowNumber => $cellXml) {
            $xmlRows .= '<row r="' . $rowNumber . '">' . implode('', $cellXml) . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>' . $xmlRows . '</sheetData>
</worksheet>';
    }

    private function cellXml(string $coordinate, string|int|float $value): string
    {
        if (is_numeric($value)) {
            return '<c r="' . $coordinate . '"><v>' . $value . '</v></c>';
        }

        return '<c r="' . $coordinate . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $value, ENT_XML1) . '</t></is></c>';
    }
}
