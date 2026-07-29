<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingBankMovement;
use App\Models\Accounting\AccountingExpense;
use App\Models\Accounting\AccountingFundingSource;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingSubsidyAllocation;
use App\Models\Accounting\AccountingSubsidyImport;
use App\Models\Accounting\AccountingSubsidyMatch;
use App\Models\Accounting\AccountingSubsidySettlement;
use App\Models\Accounting\AccountingSubsidySettlementLine;
use App\Models\EducationLevel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountingSubsidyService
{
    private const REQUIRED_CONCEPTS = [
        'normal' => ['subsidy_base', 'zone_increment', 'gratuity', 'law_19464', 'pension_reform'],
        'sep_prioritario' => ['sep_preferential', 'sep_concentration', 'additional_contribution'],
        'sep_preferente' => ['sep_preferential', 'additional_contribution'],
        'pro_retention' => ['pro_retention'],
        'school_bonus' => ['school_bonus'],
    ];

    public function __construct(
        private readonly MineducSubsidyParser $parser,
        private readonly AccountingJournalService $journalService,
        private readonly AccountingAuditService $auditService,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<string, mixed>
     */
    public function importFiles(array $files, User $user, ?string $expectedPeriod = null): array
    {
        $settlementIds = [];
        $imports = [];
        $duplicates = [];
        $pendingFiles = [];
        $seenHashes = [];

        foreach ($files as $index => $file) {
            $sha256 = hash_file('sha256', $file->getRealPath());
            if (isset($seenHashes[$sha256])) {
                $duplicates[] = [
                    'filename' => $file->getClientOriginalName(),
                    'import_id' => null,
                    'message' => 'El archivo está repetido dentro de esta carga.',
                ];

                continue;
            }
            $seenHashes[$sha256] = true;

            $duplicate = AccountingSubsidyImport::query()->where('sha256', $sha256)->first();
            if ($duplicate) {
                $duplicates[] = [
                    'filename' => $file->getClientOriginalName(),
                    'import_id' => $duplicate->id,
                    'message' => 'El archivo ya fue importado anteriormente.',
                ];

                continue;
            }

            try {
                $parsed = $this->parser->parse($file->getRealPath(), $file->getClientOriginalName());
            } catch (Throwable $exception) {
                throw ValidationException::withMessages([
                    "files.{$index}" => $file->getClientOriginalName().': '.$exception->getMessage(),
                ]);
            }

            if ($expectedPeriod !== null && $parsed['period']->format('Y-m') !== $expectedPeriod) {
                throw ValidationException::withMessages([
                    "files.{$index}" => sprintf(
                        '%s corresponde a %s y el período seleccionado es %s.',
                        $file->getClientOriginalName(),
                        $parsed['period']->format('Y-m'),
                        $expectedPeriod,
                    ),
                ]);
            }

            $pendingFiles[] = compact('file', 'sha256', 'parsed');
        }

        foreach ($pendingFiles as $pendingFile) {
            /** @var UploadedFile $file */
            $file = $pendingFile['file'];
            $sha256 = $pendingFile['sha256'];
            $parsed = $pendingFile['parsed'];
            $storagePath = sprintf(
                'accounting/subsidies/%s/%s/%s.%s',
                $parsed['period']->format('Ym'),
                $parsed['rbd'],
                $sha256,
                strtolower($file->getClientOriginalExtension() ?: ($parsed['detected_format'] === 'pdf' ? 'pdf' : 'xls')),
            );
            Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));

            try {
                [$import, $settlement] = DB::transaction(function () use ($parsed, $file, $sha256, $storagePath, $user) {
                    $import = AccountingSubsidyImport::query()->create([
                        'rbd' => $parsed['rbd'],
                        'period' => $parsed['period'],
                        'source_type' => $parsed['source_type'],
                        'original_filename' => $file->getClientOriginalName(),
                        'detected_format' => $parsed['detected_format'],
                        'sha256' => $sha256,
                        'parser_version' => MineducSubsidyParser::VERSION,
                        'status' => 'procesado',
                        'storage_path' => $storagePath,
                        'summary' => [
                            'family' => $parsed['family'],
                            'declared_total' => $parsed['declared_total'],
                            'line_count' => count($parsed['lines']),
                            'metadata' => $parsed['metadata'],
                        ],
                        'warnings' => $parsed['warnings'],
                        'errors' => [],
                        'created_by' => $user->id,
                    ]);

                    $settlement = $this->findOrCreateSettlement($parsed, $user);
                    $this->mergeSettlementMetadata($settlement, $parsed, $file->getClientOriginalName());

                    foreach ($parsed['lines'] as $lineData) {
                        $this->saveLine($settlement, $import, $lineData, $file->getClientOriginalName());
                    }

                    $this->auditService->log(
                        'importar',
                        $settlement,
                        $user,
                        [],
                        ['import_id' => $import->id, 'filename' => $file->getClientOriginalName()],
                        'Importación de liquidación o anexo MINEDUC.',
                        request(),
                    );

                    return [$import, $settlement];
                });
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($storagePath);
                throw $exception;
            }

            $imports[] = $import;
            $settlementIds[] = $settlement->id;
        }

        $settlements = AccountingSubsidySettlement::query()
            ->whereIn('id', array_values(array_unique($settlementIds)))
            ->get()
            ->map(fn (AccountingSubsidySettlement $settlement) => $this->recalculate($settlement))
            ->map(fn (AccountingSubsidySettlement $settlement) => $this->loadSettlement($settlement))
            ->values();

        return [
            'imports' => collect($imports)->map->only(['id', 'original_filename', 'status', 'warnings'])->values(),
            'duplicates' => $duplicates,
            'settlements' => $settlements,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createManual(array $payload, User $user): AccountingSubsidySettlement
    {
        $period = CarbonImmutable::parse($payload['period'])->startOfMonth();
        $family = $payload['subsidy_type'];
        $code = $this->settlementCode($payload['rbd'], $period, $family);

        $settlement = DB::transaction(function () use ($payload, $period, $family, $code, $user) {
            $existingSettlement = AccountingSubsidySettlement::query()
                ->where('rbd', $payload['rbd'])
                ->whereDate('period', $period)
                ->where('subsidy_type', $family)
                ->lockForUpdate()
                ->first();

            if ($existingSettlement) {
                throw ValidationException::withMessages([
                    'subsidy_type' => 'Ya existe una liquidación para este RBD, período y tipo de subvención.',
                ]);
            }

            /** @var AccountingSubsidySettlement $settlement */
            $settlement = AccountingSubsidySettlement::query()->create([
                'rbd' => $payload['rbd'],
                'period' => $period,
                'subsidy_type' => $family,
                'funding_source_id' => $payload['funding_source_id'] ?? $this->fundingSourceId($family),
                'code' => $code,
                'payment_date' => $payload['payment_date'] ?? null,
                'transferred_amount' => $payload['transferred_amount'] ?? null,
                'source_reference' => $payload['source_reference'] ?? null,
                'status' => 'borrador',
                'metadata' => [
                    'manual' => true,
                    'education_distribution_not_applicable' => $family === 'school_bonus',
                ],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            AccountingSubsidySettlementLine::query()->create([
                'settlement_id' => $settlement->id,
                'concept_code' => 'manual_gross',
                'concept_name' => 'Monto simple - '.$this->familyLabel($family),
                'classification' => 'haber',
                'sign' => 1,
                'amount' => $payload['gross_amount'],
                'declared_amount' => $payload['gross_amount'],
                'education_allocable' => false,
                'informative' => false,
                'metadata' => [
                    'manual' => true,
                    'education_distribution_not_applicable' => $family === 'school_bonus',
                ],
            ]);

            $this->auditService->log('crear', $settlement, $user, [], $settlement->getAttributes(), 'Liquidación manual de subvención.', request());

            return $this->recalculate($settlement);
        });

        return $this->loadSettlement($settlement);
    }

    public function approve(AccountingSubsidySettlement $settlement, User $user, ?float $transferredAmount = null): AccountingSubsidySettlement
    {
        $settlement = $this->recalculate($settlement);
        if ($settlement->status !== 'validado') {
            throw ValidationException::withMessages([
                'settlement' => 'Solo se puede aprobar una liquidación validada y aún no contabilizada.',
            ]);
        }

        $old = $settlement->getAttributes();
        if ($transferredAmount !== null) {
            $settlement->transferred_amount = $transferredAmount;
            $settlement->difference_amount = round($transferredAmount - (float) $settlement->net_amount, 2);
        }
        $settlement->status = 'aprobado';
        $settlement->approved_by = $user->id;
        $settlement->approved_at = now();
        $settlement->updated_by = $user->id;
        $settlement->save();

        $this->auditService->log('aprobar', $settlement, $user, $old, $settlement->getAttributes(), 'Liquidación de subvención aprobada.', request());

        return $this->loadSettlement($settlement);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(AccountingSubsidySettlement $settlement, array $payload, User $user): AccountingSubsidySettlement
    {
        $receivedAt = CarbonImmutable::parse($payload['received_at']);
        $postedSettlement = DB::transaction(function () use ($settlement, $payload, $user, $receivedAt) {
            $settlement = AccountingSubsidySettlement::query()
                ->lockForUpdate()
                ->findOrFail($settlement->id);

            if ($settlement->status !== 'aprobado') {
                throw ValidationException::withMessages([
                    'settlement' => 'La liquidación debe estar aprobada antes de contabilizarse.',
                ]);
            }
            if ($settlement->matches()->whereNotNull('income_id')->exists()) {
                throw ValidationException::withMessages([
                    'settlement' => 'La liquidación ya está vinculada a un ingreso contable.',
                ]);
            }

            $amount = (float) ($payload['transferred_amount'] ?? $settlement->transferred_amount ?? $settlement->net_amount);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'transferred_amount' => 'El monto transferido debe ser mayor que cero.',
                ]);
            }

            $income = AccountingIncome::query()->create([
                'code' => 'ING-'.$settlement->code,
                'received_at' => $receivedAt,
                'income_type' => 'subvencion_'.$settlement->subsidy_type,
                'funding_source_id' => $settlement->funding_source_id,
                'cost_center_id' => $payload['cost_center_id'] ?? null,
                'manual_account_id' => $payload['manual_account_id'],
                'bank_account_id' => $payload['bank_account_id'],
                'document_reference' => $payload['document_reference'] ?? $settlement->source_reference ?? $settlement->code,
                'amount' => $amount,
                'status' => 'conciliado',
                'notes' => $payload['notes'] ?? 'Ingreso generado desde liquidación de subvención '.$settlement->code.'.',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $movement = AccountingBankMovement::query()->create([
                'bank_account_id' => $payload['bank_account_id'],
                'movement_type' => 'income',
                'description' => 'Transferencia '.$settlement->code,
                'movement_date' => $receivedAt,
                'amount' => $amount,
                'status' => 'conciliado',
                'is_reconciled' => true,
                'referenceable_type' => $income->getMorphClass(),
                'referenceable_id' => $income->id,
                'notes' => 'Generado al contabilizar la liquidación.',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            AccountingSubsidyMatch::query()->create([
                'settlement_id' => $settlement->id,
                'income_id' => $income->id,
                'bank_movement_id' => $movement->id,
                'matched_amount' => $amount,
                'status' => 'conciliado',
                'matched_by' => $user->id,
                'matched_at' => now(),
            ]);

            $settlement->update([
                'payment_date' => $receivedAt,
                'transferred_amount' => $amount,
                'difference_amount' => round($amount - (float) $settlement->net_amount, 2),
                'status' => 'contabilizado',
                'updated_by' => $user->id,
            ]);

            $this->journalService->syncForIncome($income->fresh(['manualAccount']));
            $this->recalculateBankBalance((int) $payload['bank_account_id']);
            $this->auditService->log('contabilizar', $settlement, $user, [], ['income_id' => $income->id, 'amount' => $amount], 'Ingreso generado desde liquidación.', request());

            return $settlement->fresh();
        });

        return $this->loadSettlement($postedSettlement);
    }

    public function dashboard(?string $period, ?string $comparePeriod = null): array
    {
        $date = $period
            ? CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
        $comparisonDate = $comparePeriod
            ? CarbonImmutable::createFromFormat('Y-m', $comparePeriod)->startOfMonth()
            : $date->subMonth();

        $current = $this->periodDashboard($date, true);
        $comparison = $this->periodDashboard($comparisonDate);
        $metricKeys = ['income_total', 'net_liquidated', 'transferred_total', 'allocated_total', 'pie_informative_total'];
        $deltas = collect($metricKeys)->mapWithKeys(function (string $key) use ($current, $comparison) {
            $currentAmount = (float) ($current['metrics'][$key] ?? 0);
            $previousAmount = (float) ($comparison['metrics'][$key] ?? 0);

            return [$key => [
                'amount' => round($currentAmount - $previousAmount, 2),
                'percentage' => $previousAmount !== 0.0
                    ? round((($currentAmount - $previousAmount) / abs($previousAmount)) * 100, 2)
                    : null,
            ]];
        });

        $availableYears = AccountingSubsidySettlement::query()
            ->pluck('period')
            ->map(fn ($value) => CarbonImmutable::parse($value)->year)
            ->push($date->year)
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();

        return [
            ...$current,
            'comparison' => [
                'period' => $comparisonDate->format('Y-m'),
                'metrics' => $comparison['metrics'],
                'by_level' => $comparison['by_level'],
                'by_family' => $comparison['by_family'],
                'pie' => $comparison['pie'],
                'per_student' => $comparison['per_student'],
                'settlement_count' => $comparison['settlement_count'],
                'deltas' => $deltas,
            ],
            'annual' => $this->annualSummary($date->year),
            'available_years' => $availableYears,
        ];
    }

    private function periodDashboard(CarbonImmutable $date, bool $includeSettlements = false): array
    {
        $settlements = AccountingSubsidySettlement::query()
            ->with(['fundingSource:id,code,name', 'lines.allocations.educationLevel:id,name,type,order', 'matches.income:id,code,amount,status'])
            ->whereDate('period', $date)
            ->orderBy('subsidy_type')
            ->get();

        $netTotal = round((float) $settlements->sum('net_amount'), 2);
        $transferredTotal = round((float) $settlements->sum(fn ($item) => $item->transferred_amount ?? 0), 2);
        $lines = $settlements->flatMap->lines;
        $effectiveLines = $lines->where('informative', false);
        $allocations = $effectiveLines
            ->where('education_allocable', true)
            ->flatMap->allocations;
        $mappedAllocations = $allocations->whereNotNull('education_level_id');
        $unmappedAllocations = $allocations->whereNull('education_level_id');
        $allocatedTotal = round((float) $mappedAllocations->sum('amount'), 2);
        $unallocatedConceptTotal = (float) $effectiveLines
            ->where('sign', '>', 0)
            ->whereIn('classification', ['haber', 'suplemento'])
            ->sum(function (AccountingSubsidySettlementLine $line) {
                if ($line->metadata['education_distribution_not_applicable'] ?? false) {
                    return 0;
                }

                $allocated = (float) $line->allocations->sum('amount');

                return max(0, (float) $line->amount - $allocated);
            });
        $unallocatedTotal = round((float) $unmappedAllocations->sum('amount') + $unallocatedConceptTotal, 2);
        $incomeTotal = round((float) AccountingIncome::query()
            ->whereYear('received_at', $date->year)
            ->whereMonth('received_at', $date->month)
            ->where('income_type', 'like', 'subvencion_%')
            ->where('status', '!=', 'anulado')
            ->sum('amount'), 2);

        $byLevel = $allocations
            ->groupBy(fn (AccountingSubsidyAllocation $allocation) => $allocation->educationLevel?->type ?? 'sin_asignar')
            ->map(function (Collection $items, string $type) use ($allocatedTotal) {
                $amount = round((float) $items->sum('amount'), 2);

                return [
                    'key' => $type,
                    'label' => match ($type) {
                        'parvularia' => 'Educación Parvularia',
                        'basica' => 'Educación Básica',
                        'media' => 'Enseñanza Media',
                        default => 'Sin asignar',
                    },
                    'amount' => $amount,
                    'percentage' => $allocatedTotal > 0 ? round(($amount / $allocatedTotal) * 100, 2) : 0,
                ];
            })
            ->values();
        $perStudent = $this->perStudentSummary($effectiveLines, $allocations);

        $pieLines = $lines->where('concept_code', 'pie_breakdown');
        $pieAllocations = $pieLines->flatMap->allocations;
        $pieAllocationTotal = round((float) $pieAllocations->sum('amount'), 2);
        $pieTotal = round((float) $pieLines->sum('amount'), 2);
        $pieComponents = $pieLines
            ->map(fn (AccountingSubsidySettlementLine $line) => $line->metadata['pie_components'] ?? [])
            ->reduce(function (array $totals, array $components) {
                foreach ($components as $key => $value) {
                    $totals[$key] = round((float) ($totals[$key] ?? 0) + (float) $value, 2);
                }

                return $totals;
            }, []);
        $pieByLevel = $this->summarizePieAllocations(
            $pieAllocations,
            fn (AccountingSubsidyAllocation $allocation): string => $allocation->educationLevel?->type ?? 'sin_asignar',
            fn (AccountingSubsidyAllocation $allocation): string => $this->levelLabel($allocation->educationLevel?->type ?? 'sin_asignar'),
            $pieAllocationTotal,
        );
        $pieByGrade = $this->summarizePieAllocations(
            $pieAllocations,
            fn (AccountingSubsidyAllocation $allocation): string => (string) ($allocation->education_level_id ?? 'sin_asignar'),
            fn (AccountingSubsidyAllocation $allocation): string => $allocation->educationLevel?->name ?? 'Sin asignar',
            $pieAllocationTotal,
        );
        $pieByCourse = $this->summarizePieAllocations(
            $pieAllocations,
            fn (AccountingSubsidyAllocation $allocation): string => implode('|', [
                $allocation->education_level_id ?? 'sin_asignar',
                $allocation->course_letter ?: 'sin_letra',
            ]),
            fn (AccountingSubsidyAllocation $allocation): string => trim(
                ($allocation->educationLevel?->name ?? 'Sin nivel').' '.($allocation->course_letter ?? ''),
            ),
            $pieAllocationTotal,
        );

        $byFamily = $settlements
            ->groupBy('subsidy_type')
            ->map(fn (Collection $items, string $type) => [
                'key' => $type,
                'label' => $this->familyLabel($type),
                'net_amount' => round((float) $items->sum('net_amount'), 2),
                'transferred_amount' => round((float) $items->sum(fn ($item) => $item->transferred_amount ?? 0), 2),
                'difference_amount' => round((float) $items->sum(fn ($item) => $item->difference_amount ?? 0), 2),
            ])
            ->values();

        return [
            'period' => $date->format('Y-m'),
            'metrics' => [
                'income_total' => $incomeTotal,
                'net_liquidated' => $netTotal,
                'transferred_total' => $transferredTotal,
                'allocated_total' => $allocatedTotal,
                'unallocated_total' => $unallocatedTotal,
                'pie_informative_total' => $pieTotal,
                'difference_total' => round((float) $settlements->sum(fn ($item) => $item->difference_amount ?? 0), 2),
            ],
            'by_level' => $byLevel,
            'by_family' => $byFamily,
            'pie' => [
                'total' => $pieTotal,
                'allocated_total' => $pieAllocationTotal,
                'unallocated_total' => max(0, round($pieTotal - $pieAllocationTotal, 2)),
                'row_count' => $pieAllocations->count(),
                'by_level' => $pieByLevel,
                'by_grade' => $pieByGrade,
                'by_course' => $pieByCourse,
                'components' => $pieComponents,
            ],
            'per_student' => $perStudent,
            'settlement_count' => $settlements->count(),
            'settlements' => $includeSettlements ? $settlements : [],
        ];
    }

    private function annualSummary(int $year): array
    {
        $settlements = AccountingSubsidySettlement::query()
            ->with(['lines:id,settlement_id,concept_code,amount'])
            ->whereYear('period', $year)
            ->get(['id', 'period', 'net_amount', 'transferred_amount'])
            ->groupBy(fn (AccountingSubsidySettlement $settlement) => $settlement->period->format('Y-m'));
        $incomes = AccountingIncome::query()
            ->whereYear('received_at', $year)
            ->where('income_type', 'like', 'subvencion_%')
            ->where('status', '!=', 'anulado')
            ->get(['received_at', 'amount'])
            ->groupBy(fn (AccountingIncome $income) => $income->received_at->format('Y-m'));

        return collect(range(1, 12))->map(function (int $month) use ($year, $settlements, $incomes) {
            $period = CarbonImmutable::create($year, $month, 1);
            $key = $period->format('Y-m');
            $monthSettlements = $settlements->get($key, collect());

            return [
                'period' => $key,
                'label' => ucfirst($period->locale('es')->translatedFormat('M')),
                'net_liquidated' => round((float) $monthSettlements->sum('net_amount'), 2),
                'transferred_total' => round((float) $monthSettlements->sum(fn ($item) => $item->transferred_amount ?? 0), 2),
                'income_total' => round((float) $incomes->get($key, collect())->sum('amount'), 2),
                'pie_total' => round((float) $monthSettlements
                    ->flatMap->lines
                    ->where('concept_code', 'pie_breakdown')
                    ->sum('amount'), 2),
                'settlement_count' => $monthSettlements->count(),
            ];
        })->all();
    }

    /**
     * Reprocesses historical PIE imports created before row-level detail support.
     */
    public function backfillPieDetails(): int
    {
        $updated = 0;
        $lines = AccountingSubsidySettlementLine::query()
            ->where('concept_code', 'pie_breakdown')
            ->whereDoesntHave('allocations')
            ->whereNotNull('import_id')
            ->with(['import', 'settlement'])
            ->get();

        foreach ($lines as $line) {
            $import = $line->import;
            $settlement = $line->settlement;
            if (! $import || ! $settlement || ! $import->storage_path || ! Storage::disk('local')->exists($import->storage_path)) {
                continue;
            }

            try {
                $parsed = $this->parser->parse(
                    Storage::disk('local')->path($import->storage_path),
                    $import->original_filename,
                );
                $lineData = collect($parsed['lines'] ?? [])->firstWhere('concept_code', 'pie_breakdown');
                if (! $lineData || ($lineData['allocations'] ?? []) === []) {
                    continue;
                }

                DB::transaction(function () use ($settlement, $import, $lineData, $parsed): void {
                    $this->saveLine($settlement, $import, $lineData, $import->original_filename);
                    $summary = $import->summary ?? [];
                    $summary['metadata'] = $parsed['metadata'] ?? [];
                    $import->update([
                        'parser_version' => MineducSubsidyParser::VERSION,
                        'summary' => $summary,
                    ]);
                });
                $updated++;
            } catch (Throwable) {
                // A missing or legacy source must not block the accounting module.
            }
        }

        return $updated;
    }

    public function loadSettlement(AccountingSubsidySettlement $settlement): AccountingSubsidySettlement
    {
        return $settlement->load([
            'fundingSource:id,code,name',
            'lines' => fn ($query) => $query->orderBy('id'),
            'lines.allocations.educationLevel:id,name,type,order',
            'matches.income:id,code,received_at,amount,status',
            'matches.bankMovement:id,movement_date,amount,status',
        ]);
    }

    private function findOrCreateSettlement(array $parsed, User $user): AccountingSubsidySettlement
    {
        $period = CarbonImmutable::parse($parsed['period'])->startOfMonth();

        return AccountingSubsidySettlement::query()->firstOrCreate(
            [
                'rbd' => $parsed['rbd'],
                'period' => $period,
                'subsidy_type' => $parsed['family'],
            ],
            [
                'funding_source_id' => $this->fundingSourceId($parsed['family']),
                'code' => $this->settlementCode($parsed['rbd'], $period, $parsed['family']),
                'status' => 'borrador',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ],
        );
    }

    private function mergeSettlementMetadata(AccountingSubsidySettlement $settlement, array $parsed, string $filename): void
    {
        $metadata = $settlement->metadata ?? [];
        $sources = collect($metadata['sources'] ?? [])->push($filename)->unique()->values()->all();
        $metadata['sources'] = $sources;
        if (str_ends_with($parsed['source_type'], '_payment_order')) {
            $metadata['declared_net'] = $parsed['declared_total'];
        }
        $settlement->metadata = $metadata;
        $settlement->source_reference ??= $filename;
        $settlement->save();
    }

    private function saveLine(
        AccountingSubsidySettlement $settlement,
        AccountingSubsidyImport $import,
        array $lineData,
        string $filename,
    ): void {
        $existing = AccountingSubsidySettlementLine::query()
            ->where('settlement_id', $settlement->id)
            ->where('concept_code', $lineData['concept_code'])
            ->first();
        $hasAllocations = ($lineData['allocations'] ?? []) !== [];
        $preserveAllocatedAmount = ! $hasAllocations && (bool) $existing?->education_allocable;

        /** @var AccountingSubsidySettlementLine $line */
        $line = AccountingSubsidySettlementLine::query()->updateOrCreate(
            ['settlement_id' => $settlement->id, 'concept_code' => $lineData['concept_code']],
            [
                'import_id' => $hasAllocations || ! $existing ? $import->id : $existing->import_id,
                'concept_name' => $lineData['concept_name'],
                'classification' => $lineData['classification'],
                'sign' => $lineData['sign'],
                'amount' => $preserveAllocatedAmount ? $existing->amount : $lineData['amount'],
                'declared_amount' => $lineData['declared_amount'],
                'education_allocable' => $lineData['education_allocable'] || (bool) $existing?->education_allocable,
                'informative' => $lineData['informative'],
                'source_filename' => $hasAllocations || ! $existing ? $filename : $existing->source_filename,
                'metadata' => [
                    'sources' => collect($existing?->metadata['sources'] ?? [])
                        ->push($filename)
                        ->unique()
                        ->values()
                        ->all(),
                    ...($lineData['metadata'] ?? []),
                ],
            ],
        );

        if (! $hasAllocations) {
            return;
        }

        $line->allocations()->delete();
        foreach ($lineData['allocations'] as $allocation) {
            AccountingSubsidyAllocation::query()->create([
                'settlement_id' => $settlement->id,
                'line_id' => $line->id,
                'education_level_id' => $this->educationLevelId($allocation['teaching_code'], $allocation['grade_code']),
                ...$allocation,
            ]);
        }
    }

    private function recalculate(AccountingSubsidySettlement $settlement): AccountingSubsidySettlement
    {
        $settlement->load('lines');
        $effective = $settlement->lines->where('informative', false);
        $gross = (float) $effective
            ->whereIn('classification', ['haber', 'suplemento'])
            ->where('sign', '>', 0)
            ->sum('amount');
        $adjustments = (float) $effective->where('classification', 'ajuste')->sum(fn ($line) => $line->sign * (float) $line->amount);
        $deductions = (float) $effective->whereIn('classification', ['descuento', 'retencion'])->sum('amount');
        $reliquidations = (float) $effective->where('classification', 'reliquidacion')->sum(fn ($line) => $line->sign * (float) $line->amount);
        $net = (float) $effective->sum(fn ($line) => $line->sign * (float) $line->amount);
        $required = self::REQUIRED_CONCEPTS[$settlement->subsidy_type] ?? [];
        $present = $settlement->lines->pluck('concept_code')->all();
        $missing = array_values(array_diff($required, $present));
        $declaredNet = (float) (($settlement->metadata ?? [])['declared_net'] ?? 0);
        $declaredDifference = $declaredNet > 0 ? round($net - $declaredNet, 2) : 0;
        $manual = (bool) (($settlement->metadata ?? [])['manual'] ?? false);
        $complete = $manual || ($declaredNet > 0
            ? $declaredDifference === 0.0
            : $missing === []);

        $status = $settlement->status;
        if (! in_array($status, ['aprobado', 'contabilizado'], true)) {
            $status = $complete ? 'validado' : 'observado';
        }

        $metadata = $settlement->metadata ?? [];
        $metadata['missing_concepts'] = $missing;
        $metadata['declared_difference'] = $declaredDifference;

        $settlement->fill([
            'gross_amount' => round($gross, 2),
            'adjustments_amount' => round($adjustments, 2),
            'deductions_amount' => round($deductions, 2),
            'reliquidations_amount' => round($reliquidations, 2),
            'net_amount' => round($net, 2),
            'difference_amount' => $settlement->transferred_amount === null
                ? null
                : round((float) $settlement->transferred_amount - $net, 2),
            'status' => $status,
            'metadata' => $metadata,
        ])->save();

        return $settlement->fresh();
    }

    private function educationLevelId(?string $teachingCode, ?string $gradeCode): ?int
    {
        $grade = is_numeric($gradeCode) ? (int) $gradeCode : null;
        $level = match ((int) $teachingCode) {
            10 => match ($grade) {
                4 => ['order' => 1, 'type' => 'parvularia'],
                5 => ['order' => 2, 'type' => 'parvularia'],
                default => null,
            },
            110 => $grade && $grade >= 1 && $grade <= 8
                ? ['order' => $grade + 2, 'type' => 'basica']
                : null,
            310 => $grade && $grade >= 1 && $grade <= 4
                ? ['order' => $grade + 10, 'type' => 'media']
                : null,
            default => null,
        };

        return $level
            ? EducationLevel::query()
                ->where('order', $level['order'])
                ->where('type', $level['type'])
                ->value('id')
            : null;
    }

    private function fundingSourceId(string $family): ?int
    {
        $codes = in_array($family, ['sep_prioritario', 'sep_preferente'], true)
            ? ['FS-SEP']
            : ['FS-GRAL'];

        return AccountingFundingSource::query()->whereIn('code', $codes)->value('id');
    }

    private function settlementCode(string $rbd, CarbonImmutable $period, string $family): string
    {
        return 'SUB-'.$rbd.'-'.$period->format('Ym').'-'.strtoupper(str_replace('_', '-', $family));
    }

    private function familyLabel(string $family): string
    {
        return match ($family) {
            'normal' => 'Subvención Normal',
            'sep_prioritario' => 'SEP Prioritario',
            'sep_preferente' => 'SEP Preferente',
            'pro_retention' => 'Subvención Pro-Retención',
            'school_bonus' => 'Bono Escolar',
            'cd_brp' => 'CD-BRP',
            'cd_asignacion_tramo' => 'CD-ASIGNACIÓN POR TRAMO',
            'otro' => 'Otra subvención',
            default => str_replace('_', ' ', ucfirst($family)),
        };
    }

    private function levelLabel(string $type): string
    {
        return match ($type) {
            'parvularia' => 'Educación Parvularia',
            'basica' => 'Educación Básica',
            'media' => 'Enseñanza Media',
            default => 'Sin asignar',
        };
    }

    /**
     * Calculates the average educational contribution using a single
     * enrollment annex as the denominator instead of summing the repeated
     * enrollment present in every subsidy concept.
     *
     * @param  Collection<int, AccountingSubsidySettlementLine>  $effectiveLines
     * @param  Collection<int, AccountingSubsidyAllocation>  $allocations
     * @return array<string, mixed>
     */
    private function perStudentSummary(Collection $effectiveLines, Collection $allocations): array
    {
        $levels = EducationLevel::query()
            ->orderBy('order')
            ->get(['id', 'name', 'type', 'order'])
            ->keyBy('id');

        $amountByLevel = $allocations
            ->whereNotNull('education_level_id')
            ->groupBy('education_level_id')
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2));

        $enrollmentSource = 'pension_reform';
        $enrollmentAllocations = $effectiveLines
            ->where('concept_code', $enrollmentSource)
            ->flatMap->allocations
            ->filter(fn (AccountingSubsidyAllocation $allocation): bool => $allocation->education_level_id !== null
                && (float) $allocation->enrollment > 0);

        if ($enrollmentAllocations->isEmpty()) {
            $enrollmentSource = 'gratuity';
            $enrollmentAllocations = $effectiveLines
                ->where('concept_code', $enrollmentSource)
                ->flatMap->allocations
                ->filter(fn (AccountingSubsidyAllocation $allocation): bool => $allocation->education_level_id !== null
                    && (float) $allocation->enrollment > 0
                    && (float) $allocation->amount > 0);
        }

        if ($enrollmentAllocations->isEmpty()) {
            $enrollmentSource = 'available_annex';
            $enrollmentAllocations = $effectiveLines
                ->reject(fn (AccountingSubsidySettlementLine $line): bool => $line->concept_code === 'pro_retention')
                ->groupBy('concept_code')
                ->map(fn (Collection $conceptLines): Collection => $conceptLines
                    ->flatMap->allocations
                    ->filter(fn (AccountingSubsidyAllocation $allocation): bool => $allocation->education_level_id !== null
                        && (float) $allocation->enrollment > 0
                        && (float) $allocation->amount > 0))
                ->sortByDesc(fn (Collection $items): float => (float) $items->sum('enrollment'))
                ->first() ?? collect();
        }

        $enrollmentByLevel = $enrollmentAllocations
            ->groupBy('education_level_id')
            ->map(fn (Collection $items): float => round((float) $items->sum('enrollment'), 2));

        $gradeRows = $levels
            ->map(function (EducationLevel $level) use ($amountByLevel, $enrollmentByLevel): array {
                $amount = (float) $amountByLevel->get($level->id, 0);
                $enrollment = (float) $enrollmentByLevel->get($level->id, 0);

                return [
                    'key' => (string) $level->id,
                    'label' => $level->name,
                    'cycle_key' => $level->type,
                    'cycle_label' => $this->levelLabel($level->type),
                    'order' => (int) $level->order,
                    'enrollment' => $enrollment,
                    'amount' => round($amount, 2),
                    'average_per_student' => $enrollment > 0 ? round($amount / $enrollment, 2) : null,
                ];
            })
            ->filter(fn (array $row): bool => $row['amount'] !== 0.0 || $row['enrollment'] > 0)
            ->values();

        $cycleRows = $gradeRows
            ->groupBy('cycle_key')
            ->map(function (Collection $grades, string $type): array {
                $amount = round((float) $grades->sum('amount'), 2);
                $enrollment = round((float) $grades->sum('enrollment'), 2);

                return [
                    'key' => $type,
                    'label' => $this->levelLabel($type),
                    'enrollment' => $enrollment,
                    'amount' => $amount,
                    'average_per_student' => $enrollment > 0 ? round($amount / $enrollment, 2) : null,
                    'grade_count' => $grades->count(),
                ];
            })
            ->sortBy(fn (array $row): int => match ($row['key']) {
                'parvularia' => 1,
                'basica' => 2,
                'media' => 3,
                default => 99,
            })
            ->values();

        return [
            'by_cycle' => $cycleRows,
            'by_grade' => $gradeRows,
            'enrollment_total' => round((float) $enrollmentAllocations->sum('enrollment'), 2),
            'allocated_amount' => round((float) $gradeRows->sum('amount'), 2),
            'enrollment_source' => $enrollmentSource,
        ];
    }

    /**
     * @param  Collection<int, AccountingSubsidyAllocation>  $allocations
     * @return Collection<int, array<string, mixed>>
     */
    private function summarizePieAllocations(
        Collection $allocations,
        callable $groupResolver,
        callable $labelResolver,
        float $pieAllocationTotal,
    ): Collection {
        return $allocations
            ->groupBy($groupResolver)
            ->map(function (Collection $items, string|int $key) use ($labelResolver, $pieAllocationTotal) {
                /** @var AccountingSubsidyAllocation $first */
                $first = $items->first();
                $amount = round((float) $items->sum('amount'), 2);
                $sourceAmount = function (AccountingSubsidyAllocation $allocation, string $component): float {
                    return (float) data_get($allocation->source_payload, '_pie.'.$component, 0);
                };

                return [
                    'key' => (string) $key,
                    'label' => $labelResolver($first),
                    'education_level_type' => $first->educationLevel?->type ?? 'sin_asignar',
                    'education_level_order' => $first->educationLevel?->order ?? 999,
                    'course_letter' => $first->course_letter,
                    'detail_count' => $items->count(),
                    'enrollment' => round((float) $items->sum('enrollment'), 4),
                    'attendance_average' => round((float) $items->sum('attendance_average'), 4),
                    'base_amount' => round((float) $items->sum(fn (AccountingSubsidyAllocation $allocation) => $sourceAmount($allocation, 'base_amount')), 2),
                    'rurality_amount' => round((float) $items->sum(fn (AccountingSubsidyAllocation $allocation) => $sourceAmount($allocation, 'rurality_amount')), 2),
                    'zone_increment_amount' => round((float) $items->sum(fn (AccountingSubsidyAllocation $allocation) => $sourceAmount($allocation, 'zone_increment_amount')), 2),
                    'law_19464_amount' => round((float) $items->sum(fn (AccountingSubsidyAllocation $allocation) => $sourceAmount($allocation, 'law_19464_amount')), 2),
                    'amount' => $amount,
                    'percentage' => $pieAllocationTotal > 0 ? round(($amount / $pieAllocationTotal) * 100, 2) : 0,
                ];
            })
            ->sortBy([
                ['education_level_order', 'asc'],
                ['course_letter', 'asc'],
            ])
            ->values();
    }

    private function recalculateBankBalance(int $bankAccountId): void
    {
        $account = AccountingBankAccount::query()->find($bankAccountId);
        if (! $account) {
            return;
        }

        $incomeTotal = (float) AccountingIncome::query()
            ->where('bank_account_id', $bankAccountId)
            ->where('status', '!=', 'anulado')
            ->sum('amount');
        $expenseTotal = (float) AccountingExpense::query()
            ->where('bank_account_id', $bankAccountId)
            ->where('status', '!=', 'anulado')
            ->sum('total_amount');

        $account->update(['current_balance' => round($incomeTotal - $expenseTotal, 2)]);
    }
}
