<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingBudgetExecutionImport;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use Database\Seeders\AccountingModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class AccountingBudgetExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_receives_budget_execution_permissions_and_navigation(): void
    {
        $this->seed(AccountingModuleSeeder::class);

        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $permissions = [
            'contabilidad.ejecucion_presupuestaria.ver',
            'contabilidad.ejecucion_presupuestaria.importar',
            'contabilidad.ejecucion_presupuestaria.exportar',
        ];

        $this->assertEqualsCanonicalizing(
            $permissions,
            $role->permissions()->whereIn('slug', $permissions)->pluck('slug')->all(),
        );

        $module = SystemModule::query()->where('slug', 'accounting_budget_execution')->firstOrFail();
        $this->assertSame('/contabilidad/ejecucion-presupuestaria', $module->frontend_route);
        $this->assertTrue($role->modules()->whereKey($module->id)->exists());

        $superAdmin = User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->firstOrFail();
        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/contabilidad/catalogs')
            ->assertOk()
            ->assertJsonFragment(['__superadmin__']);
    }

    public function test_it_imports_an_annual_workbook_and_builds_the_dashboard(): void
    {
        $this->seed(AccountingModuleSeeder::class);
        Sanctum::actingAs(User::query()->firstOrFail());

        $this->post('/api/contabilidad/ejecucion-presupuestaria/importar', [
            'year' => 2026,
            'file' => $this->workbook(2026, 1000, 800, [100, 50], [200, 100]),
        ])
            ->assertCreated()
            ->assertJsonPath('replaced', false)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.import.school_name', 'Colegio de Prueba')
            ->assertJsonPath('data.import.reported_through_month', 2)
            ->assertJsonPath('data.metrics.income_budget', 1000)
            ->assertJsonPath('data.metrics.income_executed', 150)
            ->assertJsonPath('data.metrics.expense_budget', 800)
            ->assertJsonPath('data.metrics.expense_executed', 300)
            ->assertJsonPath('data.metrics.expense_execution_percentage', 37.5)
            ->assertJsonCount(2, 'data.accounts');

        $this->getJson('/api/contabilidad/ejecucion-presupuestaria?year=2026')
            ->assertOk()
            ->assertJsonPath('has_data', true)
            ->assertJsonPath('monthly.0.income', 100)
            ->assertJsonPath('monthly.0.expense', 200)
            ->assertJsonPath('monthly.1.balance', -50)
            ->assertJsonPath('subsidies.0.code', 'general');

        $this->assertDatabaseCount('accounting_budget_execution_imports', 1);
        $this->assertDatabaseCount('accounting_budget_execution_lines', 2);
    }

    public function test_a_new_upload_replaces_only_the_selected_year(): void
    {
        $this->seed(AccountingModuleSeeder::class);
        Sanctum::actingAs(User::query()->firstOrFail());

        $this->post('/api/contabilidad/ejecucion-presupuestaria/importar', [
            'year' => 2026,
            'file' => $this->workbook(2026, 1000, 800, [100], [200]),
        ])->assertCreated()->assertJsonPath('replaced', false);

        $firstImportId = AccountingBudgetExecutionImport::query()->where('year', 2026)->value('id');

        $this->post('/api/contabilidad/ejecucion-presupuestaria/importar', [
            'year' => 2026,
            'file' => $this->workbook(2026, 2000, 900, [500], [250]),
        ])
            ->assertCreated()
            ->assertJsonPath('replaced', true)
            ->assertJsonPath('data.metrics.income_budget', 2000)
            ->assertJsonPath('data.metrics.expense_budget', 900);

        $this->assertDatabaseCount('accounting_budget_execution_imports', 1);
        $this->assertDatabaseMissing('accounting_budget_execution_imports', ['id' => $firstImportId]);
        $this->assertDatabaseCount('accounting_budget_execution_lines', 2);
    }

    public function test_it_rejects_a_workbook_from_a_different_year_without_replacing_data(): void
    {
        $this->seed(AccountingModuleSeeder::class);
        Sanctum::actingAs(User::query()->firstOrFail());

        $this->post('/api/contabilidad/ejecucion-presupuestaria/importar', [
            'year' => 2026,
            'file' => $this->workbook(2025, 1000, 800, [100], [200]),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');

        $this->assertDatabaseCount('accounting_budget_execution_imports', 0);
        $this->assertDatabaseCount('accounting_budget_execution_lines', 0);
    }

    /** @param array<int, int|float> $incomeMonths @param array<int, int|float> $expenseMonths */
    private function workbook(int $year, float $incomeBudget, float $expenseBudget, array $incomeMonths, array $expenseMonths): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'budget-execution-').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="GENERAL" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');

        $incomeCells = $this->monthCells(11, $incomeMonths);
        $expenseCells = $this->monthCells(17, $expenseMonths);
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .$this->row(2, ['B' => 'Colegio :', 'C' => 'Colegio de Prueba'])
            .$this->row(7, ['B' => "AÑO {$year}"])
            .$this->row(9, ['B' => 'INGRESOS', 'D' => 'PRESUPUESTO', 'E' => 'ENERO'])
            .$this->row(10, ['B' => 'Subvenciones'])
            .$this->row(11, ['B' => 'Subvención General', 'C' => '$', 'D' => $incomeBudget], $incomeCells)
            .$this->row(12, ['B' => 'Total Subvenciones'])
            .$this->row(15, ['B' => 'EGRESOS', 'D' => 'PRESUPUESTO', 'E' => 'ENERO'])
            .$this->row(16, ['B' => 'Costos de Administración'])
            .$this->row(17, ['B' => 'Materiales de oficina', 'C' => '$', 'D' => $expenseBudget], $expenseCells)
            .$this->row(18, ['B' => 'Total Costos de Administración'])
            .$this->row(19, ['B' => 'TOTAL SALIDAS NETAS'])
            .'</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return new UploadedFile($path, "ejecucion-{$year}.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** @param array<string, string|int|float> $values */
    private function row(int $number, array $values, string $extraCells = ''): string
    {
        $cells = '';
        foreach ($values as $column => $value) {
            if (is_numeric($value)) {
                $cells .= "<c r=\"{$column}{$number}\"><v>{$value}</v></c>";
            } else {
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= "<c r=\"{$column}{$number}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
        }

        return "<row r=\"{$number}\">{$cells}{$extraCells}</row>";
    }

    /** @param array<int, int|float> $values */
    private function monthCells(int $row, array $values): string
    {
        $columns = ['E', 'G', 'I', 'K', 'M', 'O', 'Q', 'S', 'U', 'W', 'Y', 'AA'];
        $cells = '';
        foreach ($values as $index => $value) {
            $cells .= "<c r=\"{$columns[$index]}{$row}\"><v>{$value}</v></c>";
        }

        return $cells;
    }
}
