<?php

namespace App\Services\Remuneration\Payslips;

use App\Jobs\Remuneration\AnalyzePayslipBatch;
use App\Models\LibroDigital\School;
use App\Models\Remuneration\RemunerationFundingSourceAlias;
use App\Models\Remuneration\RemunerationPayslip;
use App\Models\Remuneration\RemunerationPayslipBatch;
use App\Models\Remuneration\RemunerationPayslipConceptRule;
use App\Models\Remuneration\RemunerationPayslipControl;
use App\Models\Remuneration\RemunerationPayslipFile;
use App\Models\Remuneration\RemunerationPayslipIssue;
use App\Models\Remuneration\RemunerationPayslipPage;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Staff;
use App\Models\User;
use App\Services\Remuneration\RemunerationAuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PayslipImportService
{
    public function __construct(
        private readonly PayslipPdfTextExtractor $extractor,
        private readonly PayslipProviderDetector $detector,
        private readonly PayslipDistributionService $distributionService,
        private readonly PayslipNormalizer $normalizer,
        private readonly RemunerationAuditService $auditService,
    ) {}

    /**
     * @param  array<int,UploadedFile>  $files
     * @param  array<string,mixed>  $options
     */
    public function stage(School $school, array $files, User $actor, array $options = []): RemunerationPayslipBatch
    {
        $duplicateAction = (string) ($options['duplicate_action'] ?? 'reject');
        $disk = (string) config('remuneration_payslips.disk', 'local');
        $storedPaths = [];

        try {
            $batch = DB::transaction(function () use ($school, $files, $actor, $options, $duplicateAction, $disk, &$storedPaths) {
                $batch = RemunerationPayslipBatch::query()->create([
                    'school_id' => $school->id,
                    'status' => 'cargado',
                    'stage' => 'carga',
                    'file_count' => count($files),
                    'options' => $options,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                foreach ($files as $file) {
                    $realPath = $file->getRealPath();
                    $sha256 = $realPath ? hash_file('sha256', $realPath) : null;
                    if (! $sha256) {
                        throw ValidationException::withMessages(['files' => 'No fue posible calcular la huella del PDF.']);
                    }

                    $previous = RemunerationPayslipFile::query()
                        ->where('school_id', $school->id)
                        ->where('sha256', $sha256)
                        ->where('status', '!=', 'anulado')
                        ->latest('version')
                        ->first();
                    if ($previous && $duplicateAction === 'reject') {
                        throw ValidationException::withMessages([
                            'files' => 'Uno de los PDF ya fue cargado para este establecimiento. Seleccione reemplazar o crear nueva versión.',
                        ]);
                    }

                    $directory = trim((string) config('remuneration_payslips.directory', 'private/remuneration/payslips'), '/')
                        .'/'.$school->id.'/'.$batch->public_id;
                    $privateName = bin2hex(random_bytes(20)).'.pdf';
                    $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $privateName);
                    if (! $storedPath) {
                        throw ValidationException::withMessages(['files' => 'No fue posible guardar el PDF en almacenamiento privado.']);
                    }
                    $storedPaths[] = $storedPath;

                    RemunerationPayslipFile::query()->create([
                        'batch_id' => $batch->id,
                        'school_id' => $school->id,
                        'replaces_file_id' => $previous?->id,
                        'sha256' => $sha256,
                        'original_filename' => basename($file->getClientOriginalName()),
                        'private_path' => $storedPath,
                        'mime_type' => $file->getMimeType() ?: 'application/pdf',
                        'size_bytes' => $file->getSize() ?: 0,
                        'status' => 'pendiente',
                        'version' => $previous ? $previous->version + 1 : 1,
                        'uploaded_by' => $actor->id,
                        'metadata' => ['duplicate_action' => $duplicateAction],
                    ]);
                }

                return $batch;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk($disk)->delete($storedPath);
            }
            throw $exception;
        }

        AnalyzePayslipBatch::dispatch($batch->id);

        $this->auditService->log(
            'cargar_liquidaciones_pdf',
            $batch,
            $actor,
            [],
            ['status' => 'cargado', 'file_count' => count($files)],
            'Carga privada de liquidaciones para análisis en cola.',
            null,
            ['batch_id' => $batch->id]
        );

        return $batch->fresh(['school:id,name,rbd', 'files']);
    }

    public function analyze(int $batchId): void
    {
        $batch = RemunerationPayslipBatch::query()->with(['files', 'school'])->findOrFail($batchId);
        if (in_array($batch->status, ['listo_revision', 'confirmado'], true)) {
            return;
        }

        $batch->update(['status' => 'procesando', 'stage' => 'deteccion', 'started_at' => $batch->started_at ?: now(), 'failure_message' => null]);
        $staffByRut = Staff::query()->whereNotNull('rut')->get(['id', 'rut'])->keyBy(fn (Staff $staff): string => $this->normalizer->rut($staff->rut));
        $aliases = RemunerationFundingSourceAlias::query()
            ->with('fundingSource:id,code,name,is_active')
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (RemunerationFundingSourceAlias $alias): string => $alias->provider.'|'.$alias->normalized_alias);
        $rules = RemunerationPayslipConceptRule::query()->where('is_active', true)->get();
        $disk = (string) config('remuneration_payslips.disk', 'local');

        foreach ($batch->files as $file) {
            if ($file->status === 'analizado' && $file->parser_version === config('remuneration_payslips.parser_version')) {
                continue;
            }

            $file->update(['status' => 'procesando']);
            $privatePath = Storage::disk($disk)->path($file->private_path);
            $pages = $this->extractor->extract($privatePath);
            foreach ($pages as $pageData) {
                $existingPage = RemunerationPayslipPage::query()
                    ->where('file_id', $file->id)
                    ->where('page_number', $pageData['page_number'])
                    ->where('status', 'analizada')
                    ->first();
                if ($existingPage) {
                    continue;
                }

                $parser = $this->detector->detect($pageData);
                $parsed = $parser->parse($pageData);
                DB::transaction(function () use ($batch, $file, $pageData, $parsed, $parser, $staffByRut, $aliases, $rules) {
                    $this->persistPage($batch, $file, $pageData, $parsed, $parser->provider(), $staffByRut, $aliases, $rules);
                });

                $batch->update([
                    'processed_pages' => RemunerationPayslipPage::query()->whereHas('file', fn ($query) => $query->where('batch_id', $batch->id))->where('status', 'analizada')->count(),
                    'stage' => 'vista_previa',
                ]);
            }

            $periods = $file->pages()->whereNotNull('period_year')->get(['period_year', 'period_month'])->unique(fn ($page) => $page->period_year.'-'.$page->period_month);
            $firstPeriod = $periods->count() === 1 ? $periods->first() : null;
            $firstPage = $file->pages()->orderBy('page_number')->first();
            $file->update([
                'provider' => $firstPage?->provider,
                'parser_version' => $firstPage?->parser_version,
                'page_count' => count($pages),
                'detected_year' => $firstPeriod?->period_year,
                'detected_month' => $firstPeriod?->period_month,
                'status' => 'analizado',
                'processed_at' => now(),
                'metadata' => array_merge($file->metadata ?? [], ['period_count' => $periods->count()]),
            ]);
        }

        $this->refreshBatchSummary($batch);
    }

    private function persistPage(
        RemunerationPayslipBatch $batch,
        RemunerationPayslipFile $file,
        array $pageData,
        array $parsed,
        string $provider,
        $staffByRut,
        $aliases,
        $rules,
    ): void {
        $year = (int) ($parsed['period']['year'] ?? 0);
        $month = (int) ($parsed['period']['month'] ?? 0);
        $rut = (string) ($parsed['employee']['rut'] ?? '');
        $staff = $staffByRut->get($rut);
        $page = RemunerationPayslipPage::query()->updateOrCreate(
            ['file_id' => $file->id, 'page_number' => $pageData['page_number']],
            [
                'provider' => $provider,
                'parser_version' => $parsed['parser_version'],
                'period_year' => $year ?: null,
                'period_month' => $month ?: null,
                'extraction_method' => $parsed['extraction_method'],
                'confidence' => $parsed['confidence'],
                'text_hash' => hash('sha256', (string) $pageData['text']),
                'normalized_text_encrypted' => $parsed['normalized_text'],
                'normalized_structure' => $parsed['normalized_structure'],
                'extracted_payload' => collect($parsed)->except(['employee', 'normalized_text'])->all(),
                'warnings' => $parsed['warnings'],
                'status' => 'analizada',
            ]
        );

        $businessKey = $this->businessKey($batch->school_id, $rut, $year, $month);
        $payslip = RemunerationPayslip::query()->updateOrCreate(
            ['page_id' => $page->id],
            [
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'school_id' => $batch->school_id,
                'staff_id' => $staff?->id,
                'rut_hash' => $this->rutHash($rut),
                'rut_encrypted' => $rut,
                'employee_name_encrypted' => $parsed['employee']['name'] ?? null,
                'business_key_hash' => $businessKey,
                'year' => $year,
                'month' => $month,
                'version' => 1,
                'is_current' => false,
                'status' => 'borrador',
                'reconciliation_status' => 'pendiente',
                'confidence' => $parsed['confidence'],
                'gross_taxable_amount' => $parsed['totals']['gross_taxable'],
                'gross_non_taxable_amount' => $parsed['totals']['gross_non_taxable'],
                'gross_total' => $parsed['totals']['gross_total'],
                'legal_deductions' => $parsed['totals']['legal_deductions'],
                'other_deductions' => $parsed['totals']['other_deductions'],
                'total_deductions' => $parsed['totals']['total_deductions'],
                'net_amount' => $parsed['totals']['net_amount'],
                'employer_contributions' => $parsed['totals']['employer_contributions'],
                'total_cost' => $parsed['totals']['gross_total'] + $parsed['totals']['employer_contributions'],
                'employment_snapshot' => collect($parsed['employee'])->except(['rut', 'name'])->all(),
                'source_totals' => $parsed['printed_funding_totals'],
                'metadata' => ['provider' => $provider, 'page_number' => $pageData['page_number']],
            ]
        );
        $page->update(['payslip_id' => $payslip->id]);

        $sourceMap = [];
        foreach ($parsed['funding_labels'] as $label) {
            $normalized = $this->normalizer->label($label);
            $alias = $aliases->get($provider.'|'.$normalized) ?: $aliases->get('*|'.$normalized);
            $sourceMap[$label] = $alias?->fundingSource;
            if (! $alias?->fundingSource?->is_active) {
                $this->issue($batch, $file, $page, $payslip, 'unknown_funding_source', 'error', 'Se detectó una subvención sin equivalencia activa.', ['label' => $normalized]);
            }
        }

        $earningModels = [];
        foreach ($parsed['earnings'] as $line) {
            $earningModels[] = $payslip->earnings()->create([
                'funding_source_id' => $sourceMap[$line['funding_label']]?->id,
                'code' => $line['code'],
                'description' => $line['description'],
                'line_number' => $line['line_number'],
                'is_imponible' => $line['is_imponible'],
                'amount' => $line['amount'],
                'source_funding_label' => $line['funding_label'],
                'original_label' => $line['original_label'],
                'page_number' => $pageData['page_number'],
            ]);
        }

        $discountModels = [];
        foreach ($parsed['discounts'] as $line) {
            $rule = $this->ruleFor($rules, $provider, 'discount', $line['code'], $line['description']);
            $classification = $rule?->classification ?: $line['classification'];
            $discountModels[] = $payslip->discounts()->create([
                'concept_rule_id' => $rule?->id,
                'code' => $line['code'],
                'description' => $line['description'],
                'classification' => $classification,
                'payment_destination' => $rule?->payment_destination ?: $line['payment_destination'],
                'distribution_base' => $rule?->distribution_base ?: $line['distribution_base'],
                'amount' => $line['amount'],
                'line_number' => $line['line_number'],
                'page_number' => $pageData['page_number'],
                'original_label' => $line['original_label'],
                'metadata' => ['parser_classification' => $line['classification']],
            ]);
        }

        foreach ($parsed['employer_contributions'] as $line) {
            $payslip->employerContributions()->create([
                'funding_source_id' => $sourceMap[$line['funding_label']]?->id,
                'code' => $line['code'],
                'description' => $line['description'],
                'source_funding_label' => $line['funding_label'],
                'amount' => $line['amount'],
                'line_number' => $line['line_number'],
                'page_number' => $pageData['page_number'],
                'original_label' => $line['original_label'],
            ]);
        }

        $distribution = $this->distributionService->distribute($parsed);
        foreach ($distribution['discount_allocations'] as $allocation) {
            $discount = $discountModels[$allocation['discount_index']] ?? null;
            $source = $sourceMap[$allocation['funding_label']] ?? null;
            if ($discount && $source) {
                $discount->allocations()->create([
                    'funding_source_id' => $source->id,
                    'base_amount' => $allocation['base_amount'],
                    'proportion' => $allocation['proportion'],
                    'calculated_amount' => $allocation['calculated_amount'],
                    'rounding_adjustment' => $allocation['rounding_adjustment'],
                    'assigned_amount' => $allocation['assigned_amount'],
                ]);
            }
        }

        foreach ($distribution['summaries'] as $summary) {
            $source = $sourceMap[$summary['funding_label']] ?? null;
            if ($source) {
                $payslip->fundingSummaries()->create([
                    'funding_source_id' => $source->id,
                    ...collect($summary)->except('funding_label')->all(),
                    'reconciliation_difference' => 0,
                    'calculation_detail' => [
                        'legal_base' => $summary['taxable_earnings'],
                        'other_base' => $summary['gross_earnings'],
                    ],
                ]);
            }
        }

        foreach ($distribution['controls'] as $control) {
            RemunerationPayslipControl::query()->create([
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'payslip_id' => $payslip->id,
                'scope' => 'payslip',
                ...$control,
            ]);
        }

        foreach ([...$parsed['warnings'], ...$distribution['issues']] as $warning) {
            $this->issue($batch, $file, $page, $payslip, $warning['code'], $warning['severity'], $warning['message']);
        }
        if (! $staff) {
            $this->issue($batch, $file, $page, $payslip, 'unmatched_staff', 'error', 'El RUT no coincide exactamente con un trabajador existente.');
        }
        if (! $year || ! $month) {
            $this->issue($batch, $file, $page, $payslip, 'missing_period', 'error', 'No fue posible detectar un período válido.');
        }
        if ($this->normalizer->label($parsed['establishment']['rbd'] ?? '') !== $this->normalizer->label($batch->school?->rbd ?? '')) {
            $this->issue($batch, $file, $page, $payslip, 'school_mismatch', 'error', 'El RBD del documento no corresponde al establecimiento seleccionado.');
        }
    }

    private function ruleFor($rules, string $provider, string $type, ?string $code, string $description): ?RemunerationPayslipConceptRule
    {
        $normalized = $this->normalizer->label($description);

        return $rules->first(fn (RemunerationPayslipConceptRule $rule): bool => in_array($rule->provider, [$provider, '*'], true)
            && $rule->line_type === $type
            && (($code && $rule->source_code === $code) || $rule->normalized_label === $normalized));
    }

    private function issue(RemunerationPayslipBatch $batch, RemunerationPayslipFile $file, RemunerationPayslipPage $page, RemunerationPayslip $payslip, string $code, string $severity, string $message, array $context = []): void
    {
        RemunerationPayslipIssue::query()->firstOrCreate([
            'batch_id' => $batch->id,
            'file_id' => $file->id,
            'page_id' => $page->id,
            'payslip_id' => $payslip->id,
            'code' => $code,
            'status' => 'abierta',
        ], [
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
        ]);
    }

    private function refreshBatchSummary(RemunerationPayslipBatch $batch): void
    {
        $payslips = RemunerationPayslip::query()->where('batch_id', $batch->id);
        $pageCount = RemunerationPayslipPage::query()->whereHas('file', fn ($query) => $query->where('batch_id', $batch->id))->count();
        $processedPages = RemunerationPayslipPage::query()->whereHas('file', fn ($query) => $query->where('batch_id', $batch->id))->where('status', 'analizada')->count();
        $payslipCount = (clone $payslips)->count();
        $grossTotal = (int) (clone $payslips)->sum('gross_total');
        $taxableTotal = (int) (clone $payslips)->sum('gross_taxable_amount');
        $nonTaxableTotal = (int) (clone $payslips)->sum('gross_non_taxable_amount');
        $deductionTotal = (int) (clone $payslips)->sum('total_deductions');
        $netTotal = (int) (clone $payslips)->sum('net_amount');
        $employerTotal = (int) (clone $payslips)->sum('employer_contributions');
        $detailGross = (int) DB::table('remuneration_payslip_earnings as e')->join('remuneration_payslips as p', 'p.id', '=', 'e.payslip_id')->where('p.batch_id', $batch->id)->sum('e.amount');
        $detailDeductions = (int) DB::table('remuneration_payslip_discounts as d')->join('remuneration_payslips as p', 'p.id', '=', 'd.payslip_id')->where('p.batch_id', $batch->id)->sum('d.amount');
        $detailEmployer = (int) DB::table('remuneration_payslip_employer_contributions as c')->join('remuneration_payslips as p', 'p.id', '=', 'c.payslip_id')->where('p.batch_id', $batch->id)->sum('c.amount');
        $distributedNet = (int) DB::table('remuneration_payslip_funding_summaries as s')->join('remuneration_payslips as p', 'p.id', '=', 's.payslip_id')->where('p.batch_id', $batch->id)->sum('s.net_amount');

        foreach ([
            $this->batchControl('batch_pages', 'Páginas analizadas = liquidaciones', $pageCount, $payslipCount),
            $this->batchControl('batch_gross_detail', 'Haberes del lote = detalle de haberes', $grossTotal, $detailGross),
            $this->batchControl('batch_deduction_detail', 'Descuentos del lote = detalle de descuentos', $deductionTotal, $detailDeductions),
            $this->batchControl('batch_distributed_net', 'Líquido del lote = distribución por subvención', $netTotal, $distributedNet),
            $this->batchControl('batch_employer_detail', 'Aportes del lote = detalle de aportes', $employerTotal, $detailEmployer),
        ] as $control) {
            RemunerationPayslipControl::query()->updateOrCreate(
                ['batch_id' => $batch->id, 'scope' => 'batch', 'code' => $control['code']],
                collect($control)->except('code')->all(),
            );
        }

        $issueErrors = RemunerationPayslipIssue::query()->where('batch_id', $batch->id)->where('status', 'abierta')->where('severity', 'error')->count();
        $controlErrors = RemunerationPayslipControl::query()->where('batch_id', $batch->id)->where('status', 'error')->count();
        $warnings = RemunerationPayslipIssue::query()->where('batch_id', $batch->id)->where('status', 'abierta')->where('severity', '!=', 'error')->count();
        $batch->update([
            'status' => 'listo_revision',
            'stage' => 'vista_previa',
            'page_count' => $pageCount,
            'processed_pages' => $processedPages,
            'payslip_count' => $payslipCount,
            'warning_count' => $warnings,
            'error_count' => $issueErrors + $controlErrors,
            'gross_total' => $grossTotal,
            'taxable_total' => $taxableTotal,
            'non_taxable_total' => $nonTaxableTotal,
            'deduction_total' => $deductionTotal,
            'net_total' => $netTotal,
            'employer_contribution_total' => $employerTotal,
            'completed_at' => now(),
            'summary' => [
                'funding_sources' => DB::table('remuneration_payslip_funding_summaries as s')
                    ->join('remuneration_payslips as p', 'p.id', '=', 's.payslip_id')
                    ->join('accounting_funding_sources as f', 'f.id', '=', 's.funding_source_id')
                    ->where('p.batch_id', $batch->id)
                    ->groupBy('f.id', 'f.code', 'f.name')
                    ->selectRaw('f.id, f.code, f.name, SUM(s.net_amount) as net_amount')
                    ->get()->toArray(),
            ],
        ]);
    }

    /** @return array{code:string,label:string,status:string,expected_amount:int,actual_amount:int,difference:int} */
    private function batchControl(string $code, string $label, int $expected, int $actual): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'status' => $expected === $actual ? 'correcto' : 'error',
            'expected_amount' => $expected,
            'actual_amount' => $actual,
            'difference' => $actual - $expected,
        ];
    }

    /** @param array<int,array<string,mixed>> $corrections */
    public function confirm(int $batchId, int $actorId, array $corrections = [], string $duplicateAction = 'reject'): void
    {
        DB::transaction(function () use ($batchId, $actorId, $corrections, $duplicateAction) {
            $batch = RemunerationPayslipBatch::query()->lockForUpdate()->findOrFail($batchId);
            if ($batch->status === 'confirmado') {
                return;
            }
            $batch->update(['status' => 'confirmando', 'stage' => 'confirmacion', 'failure_message' => null]);

            $correctionsById = collect($corrections)->keyBy('payslip_id');
            $payslips = RemunerationPayslip::query()->where('batch_id', $batch->id)->lockForUpdate()->get();
            foreach ($payslips as $payslip) {
                $correction = $correctionsById->get($payslip->public_id, []);
                $year = (int) ($correction['year'] ?? $payslip->year);
                $month = (int) ($correction['month'] ?? $payslip->month);
                $staffId = (int) ($correction['staff_id'] ?? $payslip->staff_id);
                if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) {
                    throw ValidationException::withMessages(['corrections' => 'Existe un período inválido en la confirmación.']);
                }
                $staff = Staff::query()->find($staffId);
                if (! $staff || $this->normalizer->rut($staff->rut) !== $this->normalizer->rut($payslip->rut_encrypted)) {
                    throw ValidationException::withMessages(['corrections' => 'La asociación manual debe mantener coincidencia exacta de RUT.']);
                }
                $payslip->fill([
                    'year' => $year,
                    'month' => $month,
                    'staff_id' => $staff->id,
                    'business_key_hash' => $this->businessKey($batch->school_id, $this->normalizer->rut($payslip->rut_encrypted), $year, $month),
                ])->save();
                $payslip->issues()->whereIn('code', ['unmatched_staff', 'missing_period'])->where('status', 'abierta')->update([
                    'status' => 'resuelta', 'resolution' => 'Corrección validada al confirmar.', 'resolved_at' => now(), 'resolved_by' => $actorId,
                ]);
            }

            $openErrors = RemunerationPayslipIssue::query()->where('batch_id', $batch->id)->where('status', 'abierta')->where('severity', 'error')->count();
            $controlErrors = RemunerationPayslipControl::query()->where('batch_id', $batch->id)->where('status', 'error')->count();
            if ($openErrors || $controlErrors) {
                throw ValidationException::withMessages(['batch' => 'Debe resolver todas las incidencias y controles con error antes de confirmar.']);
            }

            foreach ($payslips as $payslip) {
                $period = RemunerationPeriod::query()->firstOrCreate(
                    ['year' => $payslip->year, 'month' => $payslip->month],
                    [
                        'name' => ucfirst(Carbon::create($payslip->year, $payslip->month, 1)->locale('es')->translatedFormat('F Y')),
                        'status' => 'abierto',
                        'period_start' => Carbon::create($payslip->year, $payslip->month, 1)->toDateString(),
                        'period_end' => Carbon::create($payslip->year, $payslip->month, 1)->endOfMonth()->toDateString(),
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]
                );
                if ($period->isClosed()) {
                    throw ValidationException::withMessages(['period' => 'No se puede confirmar una liquidación en un período cerrado.']);
                }

                $previous = RemunerationPayslip::query()
                    ->where('business_key_hash', $payslip->business_key_hash)
                    ->where('is_current', true)
                    ->whereKeyNot($payslip->id)
                    ->lockForUpdate()
                    ->first();
                if ($previous && $duplicateAction === 'reject') {
                    throw ValidationException::withMessages(['batch' => 'Ya existe una liquidación vigente para un trabajador y período del lote.']);
                }
                if ($previous) {
                    $previous->update(['is_current' => false, 'status' => 'reemplazada']);
                    $payslip->previous_version_id = $previous->id;
                    $payslip->version = $previous->version + 1;
                }

                $payslip->fill([
                    'period_id' => $period->id,
                    'is_current' => true,
                    'status' => 'importada',
                    'confirmed_at' => now(),
                    'confirmed_by' => $actorId,
                ])->save();
            }

            $batch->update([
                'status' => 'confirmado',
                'stage' => 'resultado',
                'confirmed_at' => now(),
                'confirmed_by' => $actorId,
                'completed_at' => now(),
                'error_count' => 0,
            ]);
        });

        $batch = RemunerationPayslipBatch::query()->findOrFail($batchId);
        $actor = User::query()->find($actorId);
        $this->auditService->log('confirmar_liquidaciones_pdf', $batch, $actor, [], ['status' => 'confirmado'], 'Confirmación versionada de liquidaciones PDF.', null, ['batch_id' => $batch->id]);
    }

    public function fail(int $batchId): void
    {
        RemunerationPayslipBatch::query()->whereKey($batchId)->update([
            'status' => 'fallido',
            'stage' => 'error',
            'failure_message' => 'El procesamiento no pudo completarse. Puede reintentar de forma segura.',
            'completed_at' => now(),
        ]);
    }

    public function reprocess(RemunerationPayslipFile $sourceFile, User $actor): RemunerationPayslipBatch
    {
        $batch = DB::transaction(function () use ($sourceFile, $actor) {
            $batch = RemunerationPayslipBatch::query()->create([
                'school_id' => $sourceFile->school_id,
                'status' => 'cargado',
                'stage' => 'carga',
                'file_count' => 1,
                'options' => ['duplicate_action' => 'new_version', 'reprocess_source_file_id' => $sourceFile->id],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            RemunerationPayslipFile::query()->create([
                'batch_id' => $batch->id,
                'school_id' => $sourceFile->school_id,
                'replaces_file_id' => $sourceFile->id,
                'sha256' => $sourceFile->sha256,
                'original_filename' => $sourceFile->original_filename,
                'private_path' => $sourceFile->private_path,
                'mime_type' => $sourceFile->mime_type,
                'size_bytes' => $sourceFile->size_bytes,
                'status' => 'pendiente',
                'version' => $sourceFile->version + 1,
                'uploaded_by' => $actor->id,
                'metadata' => ['reprocessed_from' => $sourceFile->id],
            ]);

            return $batch;
        });
        AnalyzePayslipBatch::dispatch($batch->id);
        $this->auditService->log('reprocesar_liquidaciones_pdf', $batch, $actor, [], ['status' => 'cargado'], 'Nueva versión de procesamiento sin eliminar la anterior.', null, ['source_file_id' => $sourceFile->id]);

        return $batch->fresh(['files']);
    }

    private function rutHash(string $rut): string
    {
        return hash_hmac('sha256', $this->normalizer->rut($rut), (string) config('app.key'));
    }

    private function businessKey(int $schoolId, string $rut, int $year, int $month): string
    {
        return hash_hmac('sha256', implode('|', [$schoolId, $this->normalizer->rut($rut), $year, $month]), (string) config('app.key'));
    }
}
