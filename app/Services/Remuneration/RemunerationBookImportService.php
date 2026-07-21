<?php

namespace App\Services\Remuneration;

use App\Models\Remuneration\RemunerationBookImport;
use App\Models\Remuneration\RemunerationBookImportRow;
use App\Models\Remuneration\RemunerationPayroll;
use App\Models\Remuneration\RemunerationPayrollDistribution;
use App\Models\Remuneration\RemunerationPayrollLine;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class RemunerationBookImportService
{
    private const HEADER_ROW = 8;
    private const FIRST_DATA_ROW = 9;
    private const PAYROLL_TYPE = 'mensual';
    private const IMPORT_VERSION = 'import-v1';

    private const EARNING_SUMMARY_HEADERS = [
        'Total Imponible Tope',
        'Total Imponible',
        'Total No Imponible',
        'Tributable',
        'Total Haberes',
        'Asignación Familiar',
        'Asignacion Familiar',
    ];

    private const DEDUCTION_SUMMARY_HEADERS = [
        'Tot. D. Leg.',
        'Otros Dctos.',
        'Tot. Desc.',
        'Líquido',
        'Liquido',
    ];

    private const EMPLOYER_CONTRIBUTION_CODES = [
        '9976',
        '9977',
        '9995',
        '9997',
        '9998',
        '9999',
    ];

    public function __construct(
        private readonly RemunerationAuditService $auditService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(UploadedFile $file): array
    {
        $parsed = $this->parse($file);

        return $this->buildPreview($parsed);
    }

    public function import(UploadedFile $file, User $actor, bool $replace = false): RemunerationBookImport
    {
        $parsed = $this->parse($file);
        $preview = $this->buildPreview($parsed);

        if (($preview['summary']['error_count'] ?? 0) > 0) {
            throw ValidationException::withMessages([
                'file' => 'El libro tiene errores de validación. Revise la vista previa antes de importar.',
            ]);
        }

        return DB::transaction(function () use ($parsed, $preview, $actor, $replace) {
            $period = $this->resolvePeriod($parsed, $actor);

            if ($period->isClosed()) {
                throw ValidationException::withMessages([
                    'period_id' => 'No se puede importar en un período cerrado.',
                ]);
            }

            $existingSameHash = RemunerationBookImport::query()
                ->where('period_id', $period->id)
                ->where('file_hash', $parsed['file_hash'])
                ->where('status', 'imported')
                ->first();

            if ($existingSameHash && !$replace) {
                throw ValidationException::withMessages([
                    'file' => 'Este libro ya fue importado para el período seleccionado.',
                ]);
            }

            $staffIds = collect($preview['rows'])->pluck('staff_id')->filter()->values();
            $conflictingCalculated = RemunerationPayroll::query()
                ->where('period_id', $period->id)
                ->where('payroll_type', self::PAYROLL_TYPE)
                ->whereIn('staff_id', $staffIds)
                ->where(function ($query) {
                    $query->where('source', '!=', 'imported')
                        ->orWhereNull('source');
                })
                ->exists();

            if ($conflictingCalculated) {
                throw ValidationException::withMessages([
                    'period_id' => 'Existen liquidaciones calculadas para este período. No se reemplazan con importación.',
                ]);
            }

            $existingImported = RemunerationPayroll::query()
                ->where('period_id', $period->id)
                ->where('payroll_type', self::PAYROLL_TYPE)
                ->where('source', 'imported')
                ->exists();

            if ($existingImported && !$replace) {
                throw ValidationException::withMessages([
                    'period_id' => 'Ya existen liquidaciones importadas para este período. Active reemplazo para reimportar.',
                ]);
            }

            if ($replace) {
                $this->replaceImportedPayrolls($period, $actor);
            }

            $import = RemunerationBookImport::query()->create([
                'period_id' => $period->id,
                'original_filename' => $parsed['filename'],
                'file_hash' => $parsed['file_hash'],
                'status' => 'importing',
                'book_period' => $parsed['period_date'],
                'year' => $parsed['year'],
                'month' => $parsed['month'],
                'row_count' => $preview['summary']['row_count'],
                'matched_count' => $preview['summary']['matched_count'],
                'unmatched_count' => $preview['summary']['unmatched_count'],
                'error_count' => $preview['summary']['error_count'],
                'gross_total' => $preview['summary']['gross_total'],
                'net_total' => $preview['summary']['net_total'],
                'total_deductions' => $preview['summary']['total_deductions'],
                'employer_contributions' => $preview['summary']['employer_contributions'],
                'summary' => $preview['summary'],
                'errors' => $preview['errors'],
                'metadata' => [
                    'warnings' => $preview['warnings'],
                    'company' => $parsed['company'],
                    'rut' => $parsed['company_rut'],
                    'institution' => $parsed['institution'],
                    'rbd' => $parsed['rbd'],
                    'headers' => $preview['headers'],
                    'replace' => $replace,
                ],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $rowsByRut = collect($parsed['rows'])->keyBy(fn (array $row) => $row['rut_normalized']);
            foreach ($preview['rows'] as $previewRow) {
                $parsedRow = $rowsByRut->get($this->normalizeRut($previewRow['rut']));
                if (!$parsedRow) {
                    continue;
                }

                RemunerationBookImportRow::query()->create([
                    'book_import_id' => $import->id,
                    'staff_id' => $previewRow['staff_id'] ?: null,
                    'row_number' => $parsedRow['row_number'],
                    'rut' => $parsedRow['rut'],
                    'employee_name' => $parsedRow['employee_name'],
                    'employee_type' => $parsedRow['employee_type'],
                    'worked_days' => $parsedRow['worked_days'],
                    'weekly_hours' => $parsedRow['weekly_hours'],
                    'gross_taxable_amount' => $parsedRow['gross_taxable_amount'],
                    'gross_non_taxable_amount' => $parsedRow['gross_non_taxable_amount'],
                    'gross_total' => $parsedRow['gross_total'],
                    'taxable_amount' => $parsedRow['taxable_amount'],
                    'legal_deductions' => $parsedRow['legal_deductions'],
                    'other_deductions' => $parsedRow['other_deductions'],
                    'total_deductions' => $parsedRow['total_deductions'],
                    'employer_contributions' => $parsedRow['employer_contributions'],
                    'net_amount' => $parsedRow['net_amount'],
                    'raw_totals' => $parsedRow['raw_totals'],
                    'raw_earnings_columns' => $parsedRow['raw_earnings_columns'],
                    'raw_deductions_columns' => $parsedRow['raw_deductions_columns'],
                    'raw_earnings' => $parsedRow['raw_earnings'],
                    'raw_deductions' => $parsedRow['raw_deductions'],
                    'raw_employer_contributions' => $parsedRow['raw_employer_contributions'],
                    'errors' => [
                        'errors' => $previewRow['errors'],
                        'warnings' => $previewRow['warnings'] ?? [],
                    ],
                ]);

                if (!empty($previewRow['staff_id'])) {
                    $this->storePayroll($period, $import, $parsedRow, (int) $previewRow['staff_id'], $actor);
                }
            }

            $import->fill([
                'status' => 'imported',
                'imported_at' => now(),
                'imported_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $fresh = $import->fresh(['period', 'rows.staff', 'payrolls.staff']);
            $this->auditService->log(
                'importar_libro_remuneraciones',
                $fresh,
                $actor,
                [],
                $fresh?->getAttributes() ?? [],
                'Importación de libro externo de remuneraciones.',
                null,
                ['period_id' => $period->id, 'rows' => $preview['summary']['row_count']]
            );

            return $fresh;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (!$path) {
            throw ValidationException::withMessages(['file' => 'No se pudo leer el archivo cargado.']);
        }

        $sheets = $this->readSheets($path);
        $earningsSheet = collect($sheets)->first(fn (array $sheet) => $this->hasHeader($sheet, 'Total Haberes'));
        $deductionsSheet = collect($sheets)->first(fn (array $sheet) => $this->hasHeader($sheet, 'Líquido') || $this->hasHeader($sheet, 'Liquido'));

        if (!$earningsSheet || !$deductionsSheet) {
            throw ValidationException::withMessages([
                'file' => 'El libro debe contener una hoja de haberes y una hoja de descuentos/líquido.',
            ]);
        }

        $periodDate = $this->parsePeriodDate($this->cell($earningsSheet, 5, 2));
        $deductionPeriod = $this->parsePeriodDate($this->cell($deductionsSheet, 5, 2));
        if ($periodDate !== $deductionPeriod) {
            throw ValidationException::withMessages([
                'file' => 'Las hojas del libro tienen períodos distintos.',
            ]);
        }

        $earningsHeaders = $this->headers($earningsSheet);
        $deductionsHeaders = $this->headers($deductionsSheet);
        $deductionsByRut = $this->deductionRowsByRut($deductionsSheet, $deductionsHeaders);
        $rows = [];
        $seenRuts = [];

        foreach ($this->dataRowNumbers($earningsSheet) as $rowNumber) {
            $rut = trim((string) $this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'RUT'));
            if ($rut === '') {
                continue;
            }

            $rutNormalized = $this->normalizeRut($rut);
            $rowErrors = [];
            if (isset($seenRuts[$rutNormalized])) {
                $rowErrors[] = 'RUT duplicado dentro del libro.';
            }
            $seenRuts[$rutNormalized] = true;

            $deductionRow = $deductionsByRut[$rutNormalized] ?? null;
            if (!$deductionRow) {
                $rowErrors[] = 'No se encontró la fila equivalente en la hoja de descuentos.';
            }

            $earningsLines = $this->extractLines($earningsSheet, $earningsHeaders, $rowNumber, 'earnings');
            $deductionLines = $deductionRow
                ? $this->extractLines($deductionsSheet, $deductionsHeaders, $deductionRow['row_number'], 'deductions')
                : ['deductions' => [], 'employer_contributions' => []];

            $grossTaxable = $this->money($this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'Total Imponible'));
            $grossNonTaxable = $this->money($this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'Total No Imponible'));
            $grossTotal = $this->money($this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'Total Haberes'));
            $taxable = $this->money($this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'Tributable'));
            $grossTaxableCap = $this->money($this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'Total Imponible Tope'));
            $familyAllowance = $this->money($this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'Asignación Familiar', 'Asignacion Familiar'));
            $legalDeductions = $deductionRow ? $this->money($this->cellByHeader($deductionsSheet, $deductionsHeaders, $deductionRow['row_number'], 'Tot. D. Leg.')) : 0;
            $otherDeductions = $deductionRow ? $this->money($this->cellByHeader($deductionsSheet, $deductionsHeaders, $deductionRow['row_number'], 'Otros Dctos.')) : 0;
            $totalDeductions = $deductionRow ? $this->money($this->cellByHeader($deductionsSheet, $deductionsHeaders, $deductionRow['row_number'], 'Tot. Desc.')) : 0;
            $netAmount = $deductionRow ? $this->money($this->cellByHeader($deductionsSheet, $deductionsHeaders, $deductionRow['row_number'], 'Líquido', 'Liquido')) : 0;
            $employerContributions = collect($deductionLines['employer_contributions'])->sum('amount');

            if ($grossTotal !== $grossTaxable + $grossNonTaxable) {
                $rowErrors[] = 'Total Haberes no cuadra con imponible + no imponible.';
            }
            if ($totalDeductions !== $legalDeductions + $otherDeductions) {
                $rowErrors[] = 'Tot. Desc. no cuadra con descuentos legales + otros descuentos.';
            }
            if ($netAmount !== $grossTotal - $totalDeductions) {
                $rowErrors[] = 'Líquido no cuadra con haberes - descuentos.';
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'rut' => $rut,
                'rut_normalized' => $rutNormalized,
                'employee_name' => trim((string) $this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'Empleado')),
                'employee_type' => trim((string) $this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'Tipo Funcionario')),
                'worked_days' => $this->decimal($this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'DT')),
                'weekly_hours' => $this->decimal($this->cellByHeader($earningsSheet, $earningsHeaders, $rowNumber, 'Carga Horaria')),
                'gross_taxable_amount' => $grossTaxable,
                'gross_non_taxable_amount' => $grossNonTaxable,
                'gross_total' => $grossTotal,
                'taxable_amount' => $taxable,
                'legal_deductions' => $legalDeductions,
                'other_deductions' => $otherDeductions,
                'total_deductions' => $totalDeductions,
                'employer_contributions' => (int) $employerContributions,
                'net_amount' => $netAmount,
                'raw_totals' => [
                    'total_imponible_tope' => $grossTaxableCap,
                    'total_imponible' => $grossTaxable,
                    'total_no_imponible' => $grossNonTaxable,
                    'tributable' => $taxable,
                    'total_haberes' => $grossTotal,
                    'asignacion_familiar' => $familyAllowance,
                    'tot_d_leg' => $legalDeductions,
                    'otros_dctos' => $otherDeductions,
                    'tot_desc' => $totalDeductions,
                    'liquido' => $netAmount,
                ],
                'raw_earnings_columns' => $this->extractRowColumns($earningsSheet, $earningsHeaders, $rowNumber),
                'raw_deductions_columns' => $deductionRow
                    ? $this->extractRowColumns($deductionsSheet, $deductionsHeaders, $deductionRow['row_number'])
                    : [],
                'raw_earnings' => $earningsLines['earnings'],
                'raw_deductions' => $deductionLines['deductions'],
                'raw_employer_contributions' => $deductionLines['employer_contributions'],
                'lines' => array_values(array_merge(
                    $earningsLines['earnings'],
                    $deductionLines['deductions'],
                    $deductionLines['employer_contributions']
                )),
                'errors' => $rowErrors,
            ];
        }

        $period = Carbon::parse($periodDate);

        return [
            'filename' => $file->getClientOriginalName(),
            'file_hash' => hash_file('sha256', $path),
            'company' => trim((string) $this->cell($earningsSheet, 1, 2)),
            'company_rut' => trim((string) $this->cell($earningsSheet, 2, 2)),
            'institution' => trim((string) $this->cell($earningsSheet, 3, 2)),
            'rbd' => trim((string) $this->cell($earningsSheet, 4, 2)),
            'period_date' => $periodDate,
            'year' => (int) $period->year,
            'month' => (int) $period->month,
            'earnings_headers' => $earningsHeaders,
            'deductions_headers' => $deductionsHeaders,
            'concepts' => $this->conceptSummary($earningsHeaders, $deductionsHeaders),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildPreview(array $parsed): array
    {
        $period = RemunerationPeriod::query()
            ->where('year', $parsed['year'])
            ->where('month', $parsed['month'])
            ->first();

        $staffByRut = Staff::query()
            ->get(['id', 'full_name', 'rut', 'active'])
            ->keyBy(fn (Staff $staff) => $this->normalizeRut((string) $staff->rut));

        $rows = [];
        $errors = [];
        $warnings = [];
        $byType = [];

        foreach ($parsed['rows'] as $row) {
            $staff = $staffByRut->get($row['rut_normalized']);
            $rowErrors = $row['errors'];
            $rowWarnings = [];
            if (!$staff) {
                $rowWarnings[] = 'Funcionario no encontrado por RUT. Se guardará asociado solo al RUT del libro.';
            }

            foreach ($rowErrors as $error) {
                $errors[] = [
                    'row_number' => $row['row_number'],
                    'rut' => $row['rut'],
                    'message' => $error,
                ];
            }

            foreach ($rowWarnings as $warning) {
                $warnings[] = [
                    'row_number' => $row['row_number'],
                    'rut' => $row['rut'],
                    'message' => $warning,
                ];
            }

            $type = $row['employee_type'] ?: 'Sin tipo';
            $byType[$type] ??= [
                'type' => $type,
                'count' => 0,
                'gross_total' => 0,
                'net_amount' => 0,
                'weekly_hours' => 0,
                'worked_days' => 0,
            ];
            $byType[$type]['count']++;
            $byType[$type]['gross_total'] += $row['gross_total'];
            $byType[$type]['net_amount'] += $row['net_amount'];
            $byType[$type]['weekly_hours'] += $row['weekly_hours'];
            $byType[$type]['worked_days'] += $row['worked_days'];

            $rows[] = [
                'row_number' => $row['row_number'],
                'rut' => $row['rut'],
                'employee_name' => $row['employee_name'],
                'employee_type' => $row['employee_type'],
                'staff_id' => $staff?->id,
                'matched_staff' => $staff?->full_name,
                'gross_total' => $row['gross_total'],
                'total_deductions' => $row['total_deductions'],
                'employer_contributions' => $row['employer_contributions'],
                'net_amount' => $row['net_amount'],
                'errors' => $rowErrors,
                'warnings' => $rowWarnings,
            ];
        }

        $existingImport = $period
            ? RemunerationBookImport::query()
                ->where('period_id', $period->id)
                ->where('file_hash', $parsed['file_hash'])
                ->where('status', 'imported')
                ->first(['id', 'imported_at'])
            : null;

        $existingImportedPayrolls = $period
            ? RemunerationPayroll::query()
                ->where('period_id', $period->id)
                ->where('payroll_type', self::PAYROLL_TYPE)
                ->where('source', 'imported')
                ->count()
            : 0;

        return [
            'file' => [
                'name' => $parsed['filename'],
                'hash' => $parsed['file_hash'],
                'already_imported' => (bool) $existingImport,
                'existing_import' => $existingImport,
                'existing_imported_payrolls' => $existingImportedPayrolls,
            ],
            'period' => [
                'date' => $parsed['period_date'],
                'year' => $parsed['year'],
                'month' => $parsed['month'],
                'name' => $this->periodName($parsed['year'], $parsed['month']),
                'id' => $period?->id,
                'status' => $period?->status,
                'exists' => (bool) $period,
            ],
            'headers' => [
                'earnings_count' => count($parsed['earnings_headers']),
                'deductions_count' => count($parsed['deductions_headers']),
                'concepts' => $parsed['concepts'],
            ],
            'summary' => [
                'row_count' => count($rows),
                'matched_count' => collect($rows)->whereNotNull('staff_id')->count(),
                'unmatched_count' => collect($rows)->whereNull('staff_id')->count(),
                'error_count' => count($errors),
                'warning_count' => count($warnings),
                'gross_taxable_amount' => collect($parsed['rows'])->sum('gross_taxable_amount'),
                'gross_non_taxable_amount' => collect($parsed['rows'])->sum('gross_non_taxable_amount'),
                'gross_total' => collect($parsed['rows'])->sum('gross_total'),
                'total_deductions' => collect($parsed['rows'])->sum('total_deductions'),
                'legal_deductions' => collect($parsed['rows'])->sum('legal_deductions'),
                'other_deductions' => collect($parsed['rows'])->sum('other_deductions'),
                'employer_contributions' => collect($parsed['rows'])->sum('employer_contributions'),
                'net_total' => collect($parsed['rows'])->sum('net_amount'),
                'by_type' => array_values($byType),
            ],
            'rows' => $rows,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function resolvePeriod(array $parsed, User $actor): RemunerationPeriod
    {
        $start = Carbon::create($parsed['year'], $parsed['month'], 1)->startOfDay();

        return RemunerationPeriod::query()->firstOrCreate(
            ['year' => $parsed['year'], 'month' => $parsed['month']],
            [
                'name' => $this->periodName($parsed['year'], $parsed['month']),
                'status' => 'abierto',
                'period_start' => $start->toDateString(),
                'period_end' => $start->copy()->endOfMonth()->toDateString(),
                'notes' => 'Creado automáticamente desde importación de libro de remuneraciones.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );
    }

    private function replaceImportedPayrolls(RemunerationPeriod $period, User $actor): void
    {
        $importIds = RemunerationPayroll::query()
            ->where('period_id', $period->id)
            ->where('payroll_type', self::PAYROLL_TYPE)
            ->where('source', 'imported')
            ->pluck('book_import_id')
            ->filter()
            ->unique()
            ->values();

        RemunerationPayroll::query()
            ->where('period_id', $period->id)
            ->where('payroll_type', self::PAYROLL_TYPE)
            ->where('source', 'imported')
            ->with(['lines', 'distributions', 'payments'])
            ->get()
            ->each(function (RemunerationPayroll $payroll) {
                $payroll->lines()->delete();
                $payroll->distributions()->delete();
                $payroll->payments()->delete();
                $payroll->forceDelete();
            });

        RemunerationBookImport::query()
            ->whereIn('id', $importIds)
            ->update([
                'status' => 'replaced',
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function storePayroll(RemunerationPeriod $period, RemunerationBookImport $import, array $row, int $staffId, User $actor): void
    {
        $staff = Staff::query()->findOrFail($staffId);
        $now = now();
        $payroll = RemunerationPayroll::query()->create([
            'period_id' => $period->id,
            'staff_id' => $staffId,
            'contract_id' => null,
            'employee_profile_id' => null,
            'code' => sprintf('IMP-%04d%02d-%s', $period->year, $period->month, $row['rut_normalized']),
            'payroll_type' => self::PAYROLL_TYPE,
            'status' => 'pagada',
            'calculation_version' => self::IMPORT_VERSION,
            'source' => 'imported',
            'book_import_id' => $import->id,
            'source_row_number' => $row['row_number'],
            'calculated_at' => $now,
            'calculated_by' => $actor->id,
            'approved_at' => $now,
            'approved_by' => $actor->id,
            'paid_at' => $now,
            'paid_by' => $actor->id,
            'gross_taxable_amount' => $row['gross_taxable_amount'],
            'gross_non_taxable_amount' => $row['gross_non_taxable_amount'],
            'gross_total' => $row['gross_total'],
            'taxable_amount' => $row['taxable_amount'],
            'legal_deductions' => $row['legal_deductions'],
            'other_deductions' => $row['other_deductions'],
            'total_deductions' => $row['total_deductions'],
            'employer_contributions' => $row['employer_contributions'],
            'net_amount' => $row['net_amount'],
            'total_cost' => $row['gross_total'] + $row['employer_contributions'],
            'snapshot' => [
                'source' => 'imported_book',
                'import_id' => $import->id,
                'import_file_hash' => $import->file_hash,
                'period' => $period->only(['id', 'year', 'month', 'name', 'period_start', 'period_end', 'status']),
                'staff' => $staff->only(['id', 'full_name', 'rut', 'birth_date', 'start_date', 'contract_type', 'contract_hours', 'cargo_id']),
                'book_row' => [
                    'row_number' => $row['row_number'],
                    'rut' => $row['rut'],
                    'employee_name' => $row['employee_name'],
                    'employee_type' => $row['employee_type'],
                    'worked_days' => $row['worked_days'],
                    'weekly_hours' => $row['weekly_hours'],
                ],
                'book_columns' => [
                    'earnings' => $row['raw_earnings_columns'],
                    'deductions' => $row['raw_deductions_columns'],
                ],
                'imported_by' => $actor->id,
                'imported_at' => $now->toIso8601String(),
                'calculation_version' => self::IMPORT_VERSION,
            ],
            'observations' => 'Liquidación importada desde libro externo de remuneraciones.',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        foreach ($row['lines'] as $index => $line) {
            RemunerationPayrollLine::query()->create([
                'payroll_id' => $payroll->id,
                'concept_id' => null,
                'source_movement_id' => null,
                'line_type' => $line['line_type'],
                'code' => $line['code'],
                'name' => $line['name'],
                'is_taxable' => $line['is_taxable'],
                'is_imponible' => $line['is_imponible'],
                'affects_tax_base' => $line['affects_tax_base'],
                'affects_net' => $line['affects_net'],
                'amount' => $line['amount'],
                'quantity' => null,
                'unit_value' => null,
                'formula' => null,
                'source' => $line['source'],
                'snapshot' => [
                    'header' => $line['header'],
                    'column' => $line['column'],
                    'legal_deduction' => $line['legal_deduction'] ?? false,
                ],
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        RemunerationPayrollDistribution::query()->create([
            'payroll_id' => $payroll->id,
            'funding_source_id' => null,
            'cost_center_id' => null,
            'percentage' => 100,
            'gross_amount' => $payroll->gross_total,
            'employer_contribution_amount' => $payroll->employer_contributions,
            'deduction_amount' => $payroll->total_deductions,
            'net_amount' => $payroll->net_amount,
            'total_cost_amount' => $payroll->total_cost,
            'snapshot' => ['source' => 'imported_book'],
        ]);
    }

    /**
     * @return array<int, array{name:string,path:string,cells:array<int, array<int, mixed>>,max_row:int,max_col:int}>
     */
    private function readSheets(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'No se pudo abrir el XLSX.']);
        }

        $sharedStrings = $this->sharedStrings($zip);
        $dateStyles = $this->dateStyles($zip);
        $paths = $this->sheetPaths($zip);
        $sheets = [];

        foreach ($paths as $sheet) {
            $content = $zip->getFromName($sheet['path']);
            if ($content === false) {
                continue;
            }
            $sheets[] = [
                'name' => $sheet['name'],
                'path' => $sheet['path'],
                ...$this->parseSheet($content, $sharedStrings, $dateStyles),
            ];
        }

        $zip->close();

        if (count($sheets) < 2) {
            throw ValidationException::withMessages(['file' => 'El XLSX debe tener al menos dos hojas.']);
        }

        return $sheets;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if (!$xml) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $item) {
            $strings[] = trim($this->xmlText($item));
        }

        return $strings;
    }

    /**
     * @return array<int, bool>
     */
    private function dateStyles(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/styles.xml');
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if (!$xml) {
            return [];
        }

        $dateNumFmtIds = array_fill_keys([14, 15, 16, 17, 22, 27, 30, 36, 50, 57], true);
        if (isset($xml->numFmts)) {
            foreach ($xml->numFmts->numFmt as $format) {
                $id = (int) $format['numFmtId'];
                $code = strtolower((string) $format['formatCode']);
                if (preg_match('/[ymd]/', $code)) {
                    $dateNumFmtIds[$id] = true;
                }
            }
        }

        $styles = [];
        if (isset($xml->cellXfs)) {
            $index = 0;
            foreach ($xml->cellXfs->xf as $xf) {
                $styles[(int) $index] = isset($dateNumFmtIds[(int) $xf['numFmtId']]);
                $index++;
            }
        }

        return $styles;
    }

    /**
     * @return array<int, array{name:string,path:string}>
     */
    private function sheetPaths(ZipArchive $zip): array
    {
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));

        if (!$workbook || !$rels) {
            return [
                ['name' => 'Worksheet', 'path' => 'xl/worksheets/sheet1.xml'],
                ['name' => 'Worksheet 1', 'path' => 'xl/worksheets/sheet2.xml'],
            ];
        }

        $relMap = [];
        foreach ($rels->Relationship as $relationship) {
            $target = (string) $relationship['Target'];
            $relMap[(string) $relationship['Id']] = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/' . ltrim($target, '/');
        }

        $paths = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipId = (string) ($attributes['id'] ?? '');
            if ($relationshipId !== '' && isset($relMap[$relationshipId])) {
                $paths[] = [
                    'name' => (string) $sheet['name'],
                    'path' => $relMap[$relationshipId],
                ];
            }
        }

        return $paths ?: [
            ['name' => 'Worksheet', 'path' => 'xl/worksheets/sheet1.xml'],
            ['name' => 'Worksheet 1', 'path' => 'xl/worksheets/sheet2.xml'],
        ];
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<int, bool>  $dateStyles
     * @return array{cells:array<int, array<int, mixed>>,max_row:int,max_col:int}
     */
    private function parseSheet(string $content, array $sharedStrings, array $dateStyles): array
    {
        $xml = simplexml_load_string($content);
        if (!$xml) {
            return ['cells' => [], 'max_row' => 0, 'max_col' => 0];
        }

        $cells = [];
        $maxRow = 0;
        $maxCol = 0;
        foreach ($xml->sheetData->row as $row) {
            $rowIndex = (int) $row['r'];
            $maxRow = max($maxRow, $rowIndex);
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                [$columnIndex] = $this->coordinate($reference);
                $maxCol = max($maxCol, $columnIndex);
                $cells[$rowIndex][$columnIndex] = $this->cellValue($cell, $sharedStrings, $dateStyles);
            }
        }

        return ['cells' => $cells, 'max_row' => $maxRow, 'max_col' => $maxCol];
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<int, bool>  $dateStyles
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings, array $dateStyles): mixed
    {
        $type = (string) $cell['t'];
        $styleValue = (string) $cell['s'];
        $style = $styleValue !== '' ? (int) $styleValue : null;
        $raw = isset($cell->v) ? (string) $cell->v : null;

        if ($type === 's') {
            return $raw !== null ? ($sharedStrings[(int) $raw] ?? null) : null;
        }

        if ($type === 'inlineStr') {
            return isset($cell->is) ? trim($this->xmlText($cell->is)) : null;
        }

        if ($type === 'str') {
            return $raw;
        }

        if ($type === 'b') {
            return $raw === '1';
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        if ($style !== null && ($dateStyles[$style] ?? false) && is_numeric($raw)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $raw)->toDateString();
        }

        return is_numeric($raw) ? (float) $raw : $raw;
    }

    private function xmlText(SimpleXMLElement $element): string
    {
        $text = (string) $element;
        foreach ($element->children() as $child) {
            $text .= $this->xmlText($child);
        }

        return $text;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function coordinate(string $reference): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', strtoupper($reference), $matches);
        $letters = $matches[1] ?? 'A';
        $row = (int) ($matches[2] ?? 1);
        $column = 0;
        foreach (str_split($letters) as $letter) {
            $column = $column * 26 + (ord($letter) - 64);
        }

        return [$column, $row];
    }

    private function columnLetter(int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $column = intdiv($column - 1, 26);
        }

        return $letters;
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    private function hasHeader(array $sheet, string $header): bool
    {
        return in_array($header, $this->headers($sheet), true);
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @return array<int, string>
     */
    private function headers(array $sheet): array
    {
        $headers = [];
        foreach (($sheet['cells'][self::HEADER_ROW] ?? []) as $column => $value) {
            $normalized = $this->normalizeHeader($value);
            if ($normalized !== '') {
                $headers[(int) $column] = $normalized;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @param  array<int, string>  $headers
     * @return array<string, array{row_number:int}>
     */
    private function deductionRowsByRut(array $sheet, array $headers): array
    {
        $rows = [];
        foreach ($this->dataRowNumbers($sheet) as $rowNumber) {
            $rut = trim((string) $this->cellByHeader($sheet, $headers, $rowNumber, 'RUT'));
            if ($rut !== '') {
                $rows[$this->normalizeRut($rut)] = ['row_number' => $rowNumber];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @return array<int, int>
     */
    private function dataRowNumbers(array $sheet): array
    {
        $rows = [];
        for ($row = self::FIRST_DATA_ROW; $row <= (int) $sheet['max_row']; $row++) {
            if (count(array_filter($sheet['cells'][$row] ?? [], fn ($value) => $value !== null && trim((string) $value) !== '')) > 0) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @param  array<int, string>  $headers
     * @return array{earnings:array<int, array<string, mixed>>,deductions:array<int, array<string, mixed>>,employer_contributions:array<int, array<string, mixed>>}
     */
    private function extractLines(array $sheet, array $headers, int $rowNumber, string $sheetType): array
    {
        $lines = [
            'earnings' => [],
            'deductions' => [],
            'employer_contributions' => [],
        ];
        $totalImponibleColumn = $this->headerColumn($headers, 'Total Imponible Tope') ?: $this->headerColumn($headers, 'Total Imponible');
        $legalTotalColumn = $this->headerColumn($headers, 'Tot. D. Leg.');

        foreach ($headers as $column => $header) {
            if ($column <= 6 || $this->isSummaryHeader($header)) {
                continue;
            }

            $concept = $this->conceptFromHeader($header);
            if (!$concept['code']) {
                continue;
            }

            $amount = $this->money($this->cell($sheet, $rowNumber, $column));
            if ($amount === 0) {
                continue;
            }

            if ($sheetType === 'earnings') {
                $isImponible = $totalImponibleColumn ? $column < $totalImponibleColumn : true;
                $lines['earnings'][] = [
                    'line_type' => 'earning',
                    'code' => $concept['code'],
                    'name' => $concept['name'],
                    'amount' => $amount,
                    'is_taxable' => $isImponible,
                    'is_imponible' => $isImponible,
                    'affects_tax_base' => $isImponible,
                    'affects_net' => true,
                    'source' => 'imported_book',
                    'header' => $header,
                    'column' => $column,
                ];
                continue;
            }

            $isEmployerContribution = in_array($concept['code'], self::EMPLOYER_CONTRIBUTION_CODES, true);
            if ($isEmployerContribution) {
                $lines['employer_contributions'][] = [
                    'line_type' => 'employer_contribution',
                    'code' => $concept['code'],
                    'name' => $concept['name'],
                    'amount' => $amount,
                    'is_taxable' => false,
                    'is_imponible' => false,
                    'affects_tax_base' => false,
                    'affects_net' => false,
                    'source' => 'imported_book',
                    'header' => $header,
                    'column' => $column,
                    'legal_deduction' => false,
                ];
                continue;
            }

            $lines['deductions'][] = [
                'line_type' => 'deduction',
                'code' => $concept['code'],
                'name' => $concept['name'],
                'amount' => $amount,
                'is_taxable' => false,
                'is_imponible' => false,
                'affects_tax_base' => false,
                'affects_net' => true,
                'source' => 'imported_book',
                'header' => $header,
                'column' => $column,
                'legal_deduction' => $legalTotalColumn ? $column < $legalTotalColumn : false,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @param  array<int, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    private function extractRowColumns(array $sheet, array $headers, int $rowNumber): array
    {
        $columns = [];
        foreach ($headers as $column => $header) {
            $concept = $this->conceptFromHeader($header);
            $columns[] = [
                'column' => (int) $column,
                'letter' => $this->columnLetter((int) $column),
                'header' => $header,
                'header_display' => trim((string) $this->cell($sheet, self::HEADER_ROW, (int) $column)) ?: $header,
                'value' => $this->cell($sheet, $rowNumber, (int) $column),
                'is_concept' => (bool) $concept['code'],
                'concept_code' => $concept['code'],
                'concept_name' => $concept['code'] ? $concept['name'] : null,
                'is_summary' => $this->isSummaryHeader($header),
            ];
        }

        return $columns;
    }

    /**
     * @param  array<int, string>  $earningsHeaders
     * @param  array<int, string>  $deductionHeaders
     * @return array<string, array<int, array{code:string,name:string,header:string}>>
     */
    private function conceptSummary(array $earningsHeaders, array $deductionHeaders): array
    {
        $summary = [
            'earnings' => [],
            'deductions' => [],
            'employer_contributions' => [],
        ];

        foreach ($earningsHeaders as $column => $header) {
            if ($column <= 6 || $this->isSummaryHeader($header)) {
                continue;
            }
            $concept = $this->conceptFromHeader($header);
            if ($concept['code']) {
                $summary['earnings'][] = $concept;
            }
        }

        foreach ($deductionHeaders as $column => $header) {
            if ($column <= 6 || $this->isSummaryHeader($header)) {
                continue;
            }
            $concept = $this->conceptFromHeader($header);
            if (!$concept['code']) {
                continue;
            }
            $target = in_array($concept['code'], self::EMPLOYER_CONTRIBUTION_CODES, true)
                ? 'employer_contributions'
                : 'deductions';
            $summary[$target][] = $concept;
        }

        return $summary;
    }

    /**
     * @return array{code:?string,name:string,header:string}
     */
    private function conceptFromHeader(string $header): array
    {
        if (preg_match('/^\((\d+)\)\s*(.*)$/u', $header, $matches)) {
            return [
                'code' => $matches[1],
                'name' => trim($matches[2]) ?: $matches[1],
                'header' => $header,
            ];
        }

        return ['code' => null, 'name' => $header, 'header' => $header];
    }

    private function isSummaryHeader(string $header): bool
    {
        return in_array($header, array_merge(self::EARNING_SUMMARY_HEADERS, self::DEDUCTION_SUMMARY_HEADERS), true);
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    private function cell(array $sheet, int $row, int $column): mixed
    {
        return $sheet['cells'][$row][$column] ?? null;
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @param  array<int, string>  $headers
     */
    private function cellByHeader(array $sheet, array $headers, int $row, string ...$names): mixed
    {
        $column = $this->headerColumn($headers, ...$names);

        return $column ? $this->cell($sheet, $row, $column) : null;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function headerColumn(array $headers, string ...$names): ?int
    {
        foreach ($names as $name) {
            $normalized = $this->normalizeHeader($name);
            foreach ($headers as $column => $header) {
                if ($header === $normalized) {
                    return (int) $column;
                }
            }
        }

        return null;
    }

    private function parsePeriodDate(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'No se pudo leer el período del libro.']);
        }
    }

    private function normalizeHeader(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', (string) $value)) ?? '');
    }

    private function normalizeRut(string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9K]/i', '', $rut) ?? '');
    }

    private function money(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $normalized = trim((string) $value);
        $normalized = str_replace(['$', ' '], '', $normalized);
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (int) round((float) $normalized) : 0;
    }

    private function decimal(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : 0;
    }

    private function periodName(int $year, int $month): string
    {
        $names = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        return ($names[$month] ?? 'Período') . ' ' . $year;
    }
}
