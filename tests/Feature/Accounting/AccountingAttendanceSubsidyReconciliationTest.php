<?php

namespace Tests\Feature\Accounting;

use App\Models\AcademicYear;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingSubsidyAllocation;
use App\Models\Accounting\AccountingSubsidySettlement;
use App\Models\Accounting\AccountingSubsidySettlementLine;
use App\Models\Attendance\MonthlyAttendanceImport;
use App\Models\Attendance\MonthlyAttendanceImportRow;
use App\Models\EducationLevel;
use App\Models\User;
use Database\Seeders\AccountingModuleSeeder;
use Database\Seeders\EducationLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountingAttendanceSubsidyReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_the_expected_subsidy_and_contrasts_it_with_the_liquidation(): void
    {
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);
        $academicYear = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);

        foreach ([4, 5, 6] as $month) {
            $this->createAttendanceMonth($academicYear, $user, $month);
        }

        $this->createLiquidation('normal', 260000, $user, 5000);
        $this->createLiquidation('sep_prioritario', 74000, $user);
        $this->createLiquidation('sep_preferente', 37000, $user);
        $this->createIncome('ING-SUB-GENERAL-202607', 'subvencion_general', 260000, $user);
        $this->createIncome('ING-SUB-SEP-202607', 'subvencion_sep', 111000, $user);
        $this->createIncome('ING-PROPIO-202607', 'ingreso_propio', 900000, $user);

        $response = $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-07&jec=1&sep_category=autonomo&include_gratuity=1&concentration_band=none');

        $response
            ->assertOk()
            ->assertJsonPath('attendance_reconciliation.available', true)
            ->assertJsonPath('attendance_reconciliation.status', 'cuadrado')
            ->assertJsonPath('attendance_reconciliation.window.required_periods', ['2026-04', '2026-05', '2026-06'])
            ->assertJsonPath('attendance_reconciliation.window.found_periods', ['2026-04', '2026-05', '2026-06'])
            ->assertJsonPath('attendance_reconciliation.metrics.coverage_percentage', 100)
            ->assertJsonPath('attendance_reconciliation.metrics.attendance_rate', 100)
            ->assertJsonPath('attendance_reconciliation.metrics.full_attendance_total', 371963.02)
            ->assertJsonPath('attendance_reconciliation.metrics.attendance_loss_total', 0)
            ->assertJsonPath('attendance_reconciliation.metrics.expected_total', 371963.02)
            ->assertJsonPath('attendance_reconciliation.metrics.liquidated_gross_total', 376000)
            ->assertJsonPath('attendance_reconciliation.metrics.liquidated_total', 371000)
            ->assertJsonPath('attendance_reconciliation.metrics.difference_total', -963.02)
            ->assertJsonPath('attendance_reconciliation.metrics.excluded_liquidated_total', 5000)
            ->assertJsonPath('attendance_reconciliation.metrics.registered_income_total', 371000)
            ->assertJsonPath('attendance_reconciliation.metrics.registered_comparable_income_total', 371000)
            ->assertJsonPath('attendance_reconciliation.metrics.registered_income_unallocated_total', 111000)
            ->assertJsonPath('attendance_reconciliation.metrics.income_reference_total', 371000)
            ->assertJsonPath('attendance_reconciliation.metrics.income_reference_source', 'liquidation')
            ->assertJsonPath('attendance_reconciliation.metrics.income_difference_total', 0)
            ->assertJsonPath('attendance_reconciliation.metrics.income_model_difference_total', -963.02)
            ->assertJsonPath('attendance_reconciliation.metrics.settlement_count', 3)
            ->assertJsonPath('attendance_reconciliation.metrics.income_records_count', 2)
            ->assertJsonPath('attendance_reconciliation.metrics.income_status', 'cuadrado')
            ->assertJsonPath('attendance_reconciliation.assumptions.use_value', 36299.204)
            ->assertJsonPath('attendance_reconciliation.by_level.0.label', '1° básico')
            ->assertJsonPath('attendance_reconciliation.by_level.0.attendance_equivalent', 2)
            ->assertJsonPath('attendance_reconciliation.by_level.0.components.general', 225807.91)
            ->assertJsonPath('attendance_reconciliation.by_level.0.components.sep_priority', 73789.02)
            ->assertJsonPath('attendance_reconciliation.by_level.0.components.sep_preferential', 36894.51)
            ->assertJsonPath('attendance_reconciliation.by_subsidy.0.key', 'normal')
            ->assertJsonPath('attendance_reconciliation.by_subsidy.0.liquidated_amount', 260000)
            ->assertJsonPath('attendance_reconciliation.by_subsidy.0.liquidated_total_amount', 265000)
            ->assertJsonPath('attendance_reconciliation.by_subsidy.0.income_amount', 260000)
            ->assertJsonStructure(['attendance_reconciliation' => ['by_subsidy' => ['*' => ['full_attendance_amount', 'attendance_loss_amount']]]])
            ->assertJsonPath('attendance_reconciliation.income_records.0.code', 'ING-SUB-GENERAL-202607')
            ->assertJsonCount(1, 'attendance_reconciliation.by_level')
            ->assertJsonCount(3, 'attendance_reconciliation.by_subsidy')
            ->assertJsonCount(2, 'attendance_reconciliation.income_records');
    }

    public function test_it_marks_the_estimate_as_provisional_when_the_regulatory_window_is_incomplete(): void
    {
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);
        $academicYear = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $this->createAttendanceMonth($academicYear, $user, 4);
        $this->createAttendanceMonth($academicYear, $user, 6);

        $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-07&concentration_band=none')
            ->assertOk()
            ->assertJsonPath('attendance_reconciliation.available', true)
            ->assertJsonPath('attendance_reconciliation.status', 'incompleto')
            ->assertJsonPath('attendance_reconciliation.metrics.coverage_percentage', 66.67)
            ->assertJsonPath('attendance_reconciliation.window.missing_periods', ['2026-05']);
    }

    public function test_it_calculates_the_accumulated_reduction_against_full_attendance(): void
    {
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);
        $academicYear = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);

        foreach ([4, 5, 6] as $month) {
            $this->createAttendanceMonth($academicYear, $user, $month, 10);
        }

        $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-07&jec=1&sep_category=autonomo&include_gratuity=1&concentration_band=none')
            ->assertOk()
            ->assertJsonPath('attendance_reconciliation.status', 'sin_ingreso')
            ->assertJsonPath('attendance_reconciliation.metrics.attendance_rate', 50)
            ->assertJsonPath('attendance_reconciliation.metrics.full_attendance_total', 371963.02)
            ->assertJsonPath('attendance_reconciliation.metrics.expected_total', 185981.52)
            ->assertJsonPath('attendance_reconciliation.metrics.attendance_loss_total', 185981.5)
            ->assertJsonPath('attendance_reconciliation.metrics.attendance_loss_percentage', 50)
            ->assertJsonPath('attendance_reconciliation.by_level.0.attendance_loss_amount', 185981.5);
    }

    public function test_it_never_reports_an_excess_when_the_sep_roster_is_smaller_than_the_official_liquidation(): void
    {
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);
        $academicYear = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);

        foreach ([4, 5, 6] as $month) {
            $this->createAttendanceMonth($academicYear, $user, $month, 16);
        }

        $this->createLiquidation('normal', 250000, $user);
        $this->createLiquidation('sep_prioritario', 150000, $user);
        $this->createLiquidation('sep_preferente', 100000, $user);

        $response = $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-07&jec=1&sep_category=autonomo&include_gratuity=1&concentration_band=none')
            ->assertOk()
            ->assertJsonPath('attendance_reconciliation.metrics.baseline_source', 'mixed')
            ->assertJsonPath('attendance_reconciliation.metrics.baseline_adjusted_families', ['sep_prioritario', 'sep_preferente'])
            ->assertJsonPath('attendance_reconciliation.by_subsidy.1.baseline_source', 'liquidation_scaled_by_attendance')
            ->assertJsonPath('attendance_reconciliation.by_subsidy.2.baseline_source', 'liquidation_scaled_by_attendance');

        $metrics = $response->json('attendance_reconciliation.metrics');
        $this->assertGreaterThan($metrics['liquidated_total'], $metrics['full_attendance_total']);
        $this->assertEqualsWithDelta(
            $metrics['full_attendance_total'] - $metrics['liquidated_total'],
            $metrics['attendance_loss_total'],
            0.01,
        );
    }

    public function test_it_validates_normative_calculation_options(): void
    {
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        Sanctum::actingAs(User::query()->firstOrFail());

        $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-07&sep_category=no_valida')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sep_category');
    }

    public function test_it_maps_the_transition_level_names_used_by_the_monthly_workbook(): void
    {
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);
        $academicYear = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $this->createAttendanceMonth($academicYear, $user, 3);
        $import = MonthlyAttendanceImport::query()->firstOrFail();
        MonthlyAttendanceImportRow::query()->create([
            'monthly_attendance_import_id' => $import->id,
            'source_sheet' => 'Primer Nivel Transición A',
            'source_row' => 4,
            'source_course_name' => 'Primer Nivel Transición A',
            'source_name' => 'Alumna NT1',
            'present_days' => 18,
            'absent_days' => 2,
            'class_days' => 20,
            'attendance_rate' => 90,
            'is_sep_priority' => false,
            'is_sep_preferential' => false,
            'is_pie' => false,
            'match_status' => 'matched',
        ]);

        $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-04&concentration_band=none')
            ->assertOk()
            ->assertJsonPath('attendance_reconciliation.available', true)
            ->assertJsonPath('attendance_reconciliation.by_level.0.label', 'NT1')
            ->assertJsonPath('attendance_reconciliation.by_level.1.label', '1° básico')
            ->assertJsonCount(2, 'attendance_reconciliation.by_level');
    }

    private function createAttendanceMonth(AcademicYear $academicYear, User $user, int $month, int $presentDays = 20): void
    {
        $import = MonthlyAttendanceImport::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_year' => 2026,
            'month' => $month,
            'version' => 1,
            'is_active' => true,
            'status' => 'completed',
            'source' => 'monthly_excel',
            'original_filename' => "asistencia-2026-{$month}.xls",
            'stored_path' => "attendance/2026/{$month}.xls",
            'mime_type' => 'application/vnd.ms-excel',
            'size_bytes' => 100,
            'checksum' => hash('sha256', "attendance-2026-{$month}"),
            'sheet_count' => 1,
            'parsed_rows' => 2,
            'matched_rows' => 2,
            'unmatched_rows' => 0,
            'imported_records' => 40,
            'preserved_manual_records' => 0,
            'completed_at' => now(),
            'created_by' => $user->id,
        ]);

        foreach ([
            ['name' => 'Alumna Prioritaria', 'rut' => '11111111-1', 'priority' => true, 'preferential' => false],
            ['name' => 'Alumna Preferente', 'rut' => '22222222-2', 'priority' => false, 'preferential' => true],
        ] as $index => $student) {
            MonthlyAttendanceImportRow::query()->create([
                'monthly_attendance_import_id' => $import->id,
                'source_sheet' => '1° Básico A',
                'source_row' => $index + 2,
                'source_course_name' => '1° Básico A',
                'source_name' => $student['name'],
                'source_rut' => $student['rut'],
                'normalized_rut' => str_replace('-', '', $student['rut']),
                'present_days' => $presentDays,
                'absent_days' => 20 - $presentDays,
                'class_days' => 20,
                'attendance_rate' => ($presentDays / 20) * 100,
                'is_sep_priority' => $student['priority'],
                'is_sep_preferential' => $student['preferential'],
                'is_pie' => false,
                'match_status' => 'matched',
            ]);
        }
    }

    private function createIncome(string $code, string $type, float $amount, User $user): void
    {
        AccountingIncome::query()->create([
            'code' => $code,
            'received_at' => '2026-07-15',
            'income_type' => $type,
            'amount' => $amount,
            'status' => 'confirmado',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function createLiquidation(string $type, float $amount, User $user, float $excludedAmount = 0): void
    {
        $settlement = AccountingSubsidySettlement::query()->create([
            'code' => 'SUB-202607-'.strtoupper($type),
            'rbd' => '6830',
            'period' => '2026-07-01',
            'subsidy_type' => $type,
            'gross_amount' => $amount + $excludedAmount,
            'net_amount' => $amount + $excludedAmount,
            'status' => 'validado',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $line = AccountingSubsidySettlementLine::query()->create([
            'settlement_id' => $settlement->id,
            'concept_code' => $type === 'normal' ? 'subsidy_base' : 'sep_preferential',
            'concept_name' => 'Monto base de prueba',
            'classification' => 'haber',
            'sign' => 1,
            'amount' => $amount,
            'education_allocable' => true,
            'informative' => false,
        ]);
        AccountingSubsidyAllocation::query()->create([
            'settlement_id' => $settlement->id,
            'line_id' => $line->id,
            'education_level_id' => EducationLevel::query()->where('order', 3)->value('id'),
            'education_label' => '1° básico',
            'amount' => $amount,
            'source_row_hash' => hash('sha256', $type),
        ]);
        if ($excludedAmount > 0) {
            AccountingSubsidySettlementLine::query()->create([
                'settlement_id' => $settlement->id,
                'concept_code' => 'zone_increment',
                'concept_name' => 'Incremento zona excluido',
                'classification' => 'haber',
                'sign' => 1,
                'amount' => $excludedAmount,
                'education_allocable' => true,
                'informative' => false,
            ]);
        }
    }
}
