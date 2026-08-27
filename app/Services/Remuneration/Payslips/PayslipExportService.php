<?php

namespace App\Services\Remuneration\Payslips;

use App\Models\Accounting\AccountingFundingSource;
use App\Models\Remuneration\RemunerationPayslip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PayslipExportService
{
    public function __construct(private readonly PayslipReportingService $reportingService) {}

    /** @param array<string,mixed> $filters */
    public function workbook(array $filters): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);
        $sources = AccountingFundingSource::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $dashboard = $this->reportingService->dashboard($filters);

        $summary = $spreadsheet->createSheet()->setTitle('Resumen');
        $summaryRows = [
            ['IMPORTACIÓN DE LIQUIDACIONES DE SUELDO', null],
            ['Generado', now()->format('d-m-Y H:i')],
            ['Liquidaciones', $dashboard['metrics']['payslips']],
            ['Trabajadores', $dashboard['metrics']['workers']],
            ['Total haberes', $dashboard['metrics']['gross_total']],
            ['Total descuentos', $dashboard['metrics']['deduction_total']],
            ['Total líquido', $dashboard['metrics']['net_total']],
            ['Aportes empleador', $dashboard['metrics']['employer_total']],
            ['Costo total', $dashboard['metrics']['total_cost']],
            [],
            ['Subvención', 'Líquido', 'Previred / legales', 'Otras retenciones', 'Aportes empleador', 'Costo total'],
        ];
        foreach ($dashboard['funding_sources'] as $source) {
            $summaryRows[] = [$source['code'].' - '.$source['name'], $source['net_amount'], $source['legal_deductions'], $source['other_deductions'], $source['employer_contributions'], $source['total_cost']];
        }
        $summary->fromArray($summaryRows);
        $this->styleSheet($summary, 11, 6, [4, 5, 6, 7, 8, 9]);
        $summary->getStyle('A1:F1')->getFont()->setSize(15)->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $summary->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF334155');
        $summary->mergeCells('A1:F1');

        $workers = $spreadsheet->createSheet()->setTitle('Matriz Funcionarios');
        $workerHeaders = ['Período', 'Trabajador', 'RUT', 'Estado', 'Haberes', 'Descuentos', 'Líquido', 'Aportes empleador', 'Costo total'];
        foreach ($sources as $source) {
            foreach (['Imponible', 'No imponible', 'Haberes', 'Desc. legales', 'Otros desc.', 'Líquido', 'Aportes', 'Costo total'] as $metric) {
                $workerHeaders[] = $source->code.' '.$metric;
            }
        }
        $workers->fromArray([$workerHeaders]);
        $row = 2;
        $this->payslips($filters)->with(['staff:id,full_name,rut', 'period:id,name', 'fundingSummaries'])->chunkById(300, function ($payslips) use ($workers, $sources, &$row) {
            foreach ($payslips as $payslip) {
                $values = [$payslip->period?->name, $payslip->staff?->full_name, $payslip->staff?->rut, $payslip->reconciliation_status, $payslip->gross_total, $payslip->total_deductions, $payslip->net_amount, $payslip->employer_contributions, $payslip->total_cost];
                $bySource = $payslip->fundingSummaries->keyBy('funding_source_id');
                foreach ($sources as $source) {
                    $summary = $bySource->get($source->id);
                    array_push($values,
                        (int) ($summary?->taxable_earnings ?? 0),
                        (int) ($summary?->non_taxable_earnings ?? 0),
                        (int) ($summary?->gross_earnings ?? 0),
                        (int) ($summary?->legal_deductions ?? 0),
                        (int) ($summary?->other_deductions ?? 0),
                        (int) ($summary?->net_amount ?? 0),
                        (int) ($summary?->employer_contributions ?? 0),
                        (int) ($summary?->total_cost ?? 0),
                    );
                }
                $workers->fromArray([$values], null, 'A'.$row++);
            }
        });
        $this->styleSheet($workers, 1, count($workerHeaders), range(5, count($workerHeaders)));

        $earnings = $spreadsheet->createSheet()->setTitle('Matriz Haberes');
        $earningsHeaders = ['Período', 'Trabajador', 'RUT', 'Código', 'Descripción', 'Tipo', 'Subvención', 'Monto', 'Página'];
        $earnings->fromArray([$earningsHeaders]);
        $earningQuery = DB::table('remuneration_payslip_earnings as e')
            ->join('remuneration_payslips as p', 'p.id', '=', 'e.payslip_id')
            ->leftJoin('staff as st', 'st.id', '=', 'p.staff_id')
            ->leftJoin('remuneration_periods as rp', 'rp.id', '=', 'p.period_id')
            ->leftJoin('accounting_funding_sources as f', 'f.id', '=', 'e.funding_source_id')
            ->where('p.is_current', true)->where('p.status', 'importada');
        $this->applySqlFilters($earningQuery, $filters, 'p');
        $earningQuery->selectRaw('e.id as id, rp.name as period, st.full_name as worker, st.rut, e.code, e.description, e.is_imponible, f.code as funding_source, e.amount, e.page_number');
        $row = 2;
        $earningQuery->orderBy('e.id')->chunkById(1000, function ($lines) use ($earnings, &$row) {
            foreach ($lines as $line) {
                $earnings->fromArray([[$line->period, $line->worker, $line->rut, $line->code, $line->description, $line->is_imponible ? 'Imponible' : 'No imponible', $line->funding_source, (int) $line->amount, (int) $line->page_number]], null, 'A'.$row++);
            }
        }, 'e.id', 'id');
        $this->styleSheet($earnings, 1, count($earningsHeaders), [8]);

        $discounts = $spreadsheet->createSheet()->setTitle('Descuentos');
        $discountHeaders = ['Período', 'Trabajador', 'RUT', 'Código', 'Descripción', 'Clasificación', 'Destino', 'Monto original', 'Base distribución', 'Subvención', 'Base', 'Proporción', 'Calculado', 'Ajuste', 'Asignado'];
        $discounts->fromArray([$discountHeaders]);
        $discountQuery = DB::table('remuneration_payslip_discount_allocations as a')
            ->join('remuneration_payslip_discounts as d', 'd.id', '=', 'a.discount_id')
            ->join('remuneration_payslips as p', 'p.id', '=', 'd.payslip_id')
            ->leftJoin('staff as st', 'st.id', '=', 'p.staff_id')
            ->leftJoin('remuneration_periods as rp', 'rp.id', '=', 'p.period_id')
            ->join('accounting_funding_sources as f', 'f.id', '=', 'a.funding_source_id')
            ->where('p.is_current', true)->where('p.status', 'importada');
        $this->applySqlFilters($discountQuery, $filters, 'p');
        $discountQuery->selectRaw('a.id as id, rp.name as period, st.full_name as worker, st.rut, d.code, d.description, d.classification, d.payment_destination, d.amount as original_amount, d.distribution_base, f.code as funding_source, a.base_amount, a.proportion, a.calculated_amount, a.rounding_adjustment, a.assigned_amount');
        $row = 2;
        $discountQuery->orderBy('a.id')->chunkById(1000, function ($lines) use ($discounts, &$row) {
            foreach ($lines as $line) {
                $discounts->fromArray([[$line->period, $line->worker, $line->rut, $line->code, $line->description, $line->classification, $line->payment_destination, (int) $line->original_amount, $line->distribution_base, $line->funding_source, (int) $line->base_amount, (float) $line->proportion, (float) $line->calculated_amount, (int) $line->rounding_adjustment, (int) $line->assigned_amount]], null, 'A'.$row++);
            }
        }, 'a.id', 'id');
        $this->styleSheet($discounts, 1, count($discountHeaders), [8, 11, 13, 14, 15]);

        $contributions = $spreadsheet->createSheet()->setTitle('Aportes Empleador');
        $contributionHeaders = ['Período', 'Trabajador', 'RUT', 'Código', 'Descripción', 'Subvención', 'Monto', 'Página'];
        $contributions->fromArray([$contributionHeaders]);
        $contributionQuery = DB::table('remuneration_payslip_employer_contributions as c')
            ->join('remuneration_payslips as p', 'p.id', '=', 'c.payslip_id')
            ->leftJoin('staff as st', 'st.id', '=', 'p.staff_id')
            ->leftJoin('remuneration_periods as rp', 'rp.id', '=', 'p.period_id')
            ->leftJoin('accounting_funding_sources as f', 'f.id', '=', 'c.funding_source_id')
            ->where('p.is_current', true)->where('p.status', 'importada');
        $this->applySqlFilters($contributionQuery, $filters, 'p');
        $contributionQuery->selectRaw('c.id as id, rp.name as period, st.full_name as worker, st.rut, c.code, c.description, f.code as funding_source, c.amount, c.page_number');
        $row = 2;
        $contributionQuery->orderBy('c.id')->chunkById(1000, function ($lines) use ($contributions, &$row) {
            foreach ($lines as $line) {
                $contributions->fromArray([[$line->period, $line->worker, $line->rut, $line->code, $line->description, $line->funding_source, (int) $line->amount, (int) $line->page_number]], null, 'A'.$row++);
            }
        }, 'c.id', 'id');
        $this->styleSheet($contributions, 1, count($contributionHeaders), [7]);

        $controls = $spreadsheet->createSheet()->setTitle('Controles');
        $controlHeaders = ['Período', 'Trabajador', 'RUT', 'Control', 'Estado', 'Esperado', 'Actual', 'Diferencia'];
        $controls->fromArray([$controlHeaders]);
        $controlQuery = DB::table('remuneration_payslip_controls as c')
            ->join('remuneration_payslips as p', 'p.id', '=', 'c.payslip_id')
            ->leftJoin('staff as st', 'st.id', '=', 'p.staff_id')
            ->leftJoin('remuneration_periods as rp', 'rp.id', '=', 'p.period_id')
            ->where('p.is_current', true)->where('p.status', 'importada');
        $this->applySqlFilters($controlQuery, $filters, 'p');
        $controlQuery->selectRaw('c.id as id, rp.name as period, st.full_name as worker, st.rut, c.label, c.status as control_status, c.expected_amount, c.actual_amount, c.difference');
        $row = 2;
        $controlQuery->orderBy('c.id')->chunkById(1000, function ($lines) use ($controls, &$row) {
            foreach ($lines as $line) {
                $controls->fromArray([[$line->period, $line->worker, $line->rut, $line->label, $line->control_status, $line->expected_amount, $line->actual_amount, $line->difference]], null, 'A'.$row++);
            }
        }, 'c.id', 'id');
        $this->styleSheet($controls, 1, count($controlHeaders), [6, 7, 8]);

        $methodology = $spreadsheet->createSheet()->setTitle('Metodología');
        $methodology->fromArray([
            ['METODOLOGÍA DE DISTRIBUCIÓN', null],
            ['Haberes por subvención', 'H_s = imponibles + no imponibles de la subvención.'],
            ['Descuentos legales', 'Cada línea: DL_j × I_s / suma(I_s).'],
            ['Otros descuentos', 'Cada línea: DO_j × H_s / suma(H_s).'],
            ['Líquido por subvención', 'H_s - descuentos legales asignados - otros descuentos asignados.'],
            ['Redondeo', 'Pesos enteros por línea; la última subvención activa absorbe el residuo.'],
            ['Control', 'Cada descuento y el líquido total deben cuadrar exactamente; diferencias sustantivas nunca se ajustan en silencio.'],
            ['Trazabilidad', 'Archivo SHA-256, proveedor, parser, página, versión, base, proporción, cálculo y ajuste.'],
            ['Privacidad', 'Archivos en almacenamiento privado; esta exportación requiere permiso específico.'],
        ]);
        $this->styleSheet($methodology, 1, 2, []);
        $methodology->getColumnDimension('A')->setWidth(30);
        $methodology->getColumnDimension('B')->setWidth(110);
        $methodology->getStyle('B1:B20')->getAlignment()->setWrapText(true);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** @param resource $stream @param array<string,mixed> $filters */
    public function writeCsv($stream, string $type, array $filters): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        $table = match ($type) {
            'discounts' => 'remuneration_payslip_discounts',
            'contributions' => 'remuneration_payslip_employer_contributions',
            default => 'remuneration_payslip_earnings',
        };
        $alias = match ($type) {
            'discounts' => 'd', 'contributions' => 'c', default => 'e'
        };
        fputcsv($stream, ['Período', 'Trabajador', 'RUT', 'Código', 'Descripción', 'Monto'], ';');
        $query = DB::table($table.' as '.$alias)
            ->join('remuneration_payslips as p', 'p.id', '=', $alias.'.payslip_id')
            ->leftJoin('staff as st', 'st.id', '=', 'p.staff_id')
            ->leftJoin('remuneration_periods as rp', 'rp.id', '=', 'p.period_id')
            ->where('p.is_current', true)->where('p.status', 'importada');
        $this->applySqlFilters($query, $filters, 'p');
        $query->orderBy($alias.'.id')->selectRaw("{$alias}.id, rp.name as period, st.full_name as worker, st.rut, {$alias}.code, {$alias}.description, {$alias}.amount")
            ->chunkById(1000, function ($rows) use ($stream) {
                foreach ($rows as $row) {
                    fputcsv($stream, [$row->period, $row->worker, $row->rut, $row->code, $row->description, (int) $row->amount], ';');
                }
            }, $alias.'.id', 'id');
    }

    private function payslips(array $filters): Builder
    {
        return RemunerationPayslip::query()->where('is_current', true)->where('status', 'importada')
            ->when($filters['school_id'] ?? null, fn (Builder $query, $value) => $query->where('school_id', $value))
            ->when($filters['year'] ?? null, fn (Builder $query, $value) => $query->where('year', $value))
            ->when($filters['month'] ?? null, fn (Builder $query, $value) => $query->where('month', $value))
            ->when($filters['month_from'] ?? null, fn (Builder $query, $value) => $query->where('month', '>=', $value))
            ->when($filters['month_to'] ?? null, fn (Builder $query, $value) => $query->where('month', '<=', $value))
            ->when($filters['staff_id'] ?? null, fn (Builder $query, $value) => $query->where('staff_id', $value));
    }

    private function applySqlFilters($query, array $filters, string $alias): void
    {
        foreach (['school_id', 'year', 'month', 'staff_id'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($alias.'.'.$field, $filters[$field]);
            }
        }
        $query->when($filters['month_from'] ?? null, fn ($query, $value) => $query->where($alias.'.month', '>=', $value));
        $query->when($filters['month_to'] ?? null, fn ($query, $value) => $query->where($alias.'.month', '<=', $value));
    }

    private function styleSheet($sheet, int $headerRow, int $columnCount, array $moneyColumns): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex(max(1, $columnCount));
        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->setAutoFilter('A'.$headerRow.':'.$lastColumn.$headerRow);
        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF556EE6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        for ($column = 1; $column <= $columnCount; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        foreach ($moneyColumns as $column) {
            if ($column <= $columnCount) {
                $sheet->getStyle(Coordinate::stringFromColumnIndex($column).'1:'.Coordinate::stringFromColumnIndex($column).$sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0');
            }
        }
        $sheet->getSheetView()->setZoomScale(90);
    }
}
