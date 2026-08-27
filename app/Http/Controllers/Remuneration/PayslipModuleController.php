<?php

namespace App\Http\Controllers\Remuneration;

use App\Http\Controllers\Controller;
use App\Jobs\Remuneration\ConfirmPayslipBatch;
use App\Models\Accounting\AccountingFundingSource;
use App\Models\LibroDigital\School;
use App\Models\Remuneration\RemunerationFundingSourceAlias;
use App\Models\Remuneration\RemunerationPaymentProposal;
use App\Models\Remuneration\RemunerationPayslip;
use App\Models\Remuneration\RemunerationPayslipBatch;
use App\Models\Remuneration\RemunerationPayslipFile;
use App\Models\Remuneration\RemunerationPayslipIssue;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Staff;
use App\Services\Remuneration\Payslips\PayslipExportService;
use App\Services\Remuneration\Payslips\PayslipImportService;
use App\Services\Remuneration\Payslips\PayslipNormalizer;
use App\Services\Remuneration\Payslips\PayslipPaymentProposalService;
use App\Services\Remuneration\Payslips\PayslipReportingService;
use App\Services\Remuneration\RemunerationAccessService;
use App\Services\Remuneration\RemunerationAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayslipModuleController extends Controller
{
    public function __construct(
        private readonly RemunerationAccessService $access,
        private readonly PayslipImportService $imports,
        private readonly PayslipReportingService $reporting,
        private readonly PayslipExportService $exports,
        private readonly PayslipPaymentProposalService $proposals,
        private readonly PayslipNormalizer $normalizer,
        private readonly RemunerationAuditService $audit,
    ) {}

    public function catalogs(Request $request): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);

        return response()->json([
            'schools' => School::query()->where('active', true)->orderBy('name')->get(['id', 'public_id', 'name', 'rbd']),
            'periods' => RemunerationPeriod::query()->orderByDesc('year')->orderByDesc('month')->get(['id', 'name', 'year', 'month', 'status']),
            'funding_sources' => AccountingFundingSource::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'permissions' => $request->user()?->permissionSlugs() ?? [],
            'months' => collect(range(1, 12))->map(fn (int $month): array => ['value' => $month, 'label' => ucfirst(now()->month($month)->locale('es')->translatedFormat('F'))]),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);

        return response()->json($this->reporting->dashboard($this->filters($request)));
    }

    public function batches(Request $request): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);
        $query = RemunerationPayslipBatch::query()
            ->with(['school:id,name,rbd', 'createdBy:id,name'])
            ->when($request->integer('school_id'), fn ($query, $value) => $query->where('school_id', $value))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id');

        return response()->json($query->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function stage(Request $request): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_IMPORT_PERMISSION);
        $payload = Validator::make($request->all(), [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'files' => ['required', 'array', 'min:1', 'max:'.config('remuneration_payslips.upload.max_files', 20)],
            'files.*' => ['required', 'file', 'mimes:pdf', 'max:'.config('remuneration_payslips.upload.max_file_kb', 51200)],
            'duplicate_action' => ['nullable', Rule::in(['reject', 'replace', 'new_version'])],
        ])->validate();
        $batch = $this->imports->stage(
            School::query()->findOrFail($payload['school_id']),
            $request->file('files'),
            $request->user(),
            ['duplicate_action' => $payload['duplicate_action'] ?? 'reject'],
        );

        return response()->json(['message' => 'Los PDF quedaron en cola para análisis privado.', 'data' => $batch], 202);
    }

    public function showBatch(Request $request, RemunerationPayslipBatch $batch): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);
        $batch->load(['school:id,name,rbd', 'files', 'issues' => fn ($query) => $query->latest('id')->limit(250)]);
        $payslips = $batch->payslips()
            ->with(['staff:id,full_name,rut', 'fundingSummaries.fundingSource:id,code,name', 'controls:id,payslip_id,code,label,status,difference'])
            ->orderBy('year')->orderBy('month')->orderBy('id')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))))
            ->through(fn (RemunerationPayslip $payslip): array => [
                'id' => $payslip->public_id,
                'page' => $payslip->metadata['page_number'] ?? null,
                'year' => $payslip->year,
                'month' => $payslip->month,
                'worker' => $payslip->staff?->full_name ?: $payslip->employee_name_encrypted,
                'rut' => $payslip->maskedRut(),
                'staff_id' => $payslip->staff_id,
                'matched' => (bool) $payslip->staff_id,
                'confidence' => (float) $payslip->confidence,
                'gross_total' => $payslip->gross_total,
                'deductions' => $payslip->total_deductions,
                'net_amount' => $payslip->net_amount,
                'employer_contributions' => $payslip->employer_contributions,
                'funding_sources' => $payslip->fundingSummaries->map(fn ($summary): array => [
                    'code' => $summary->fundingSource?->code,
                    'gross' => $summary->gross_earnings,
                    'net' => $summary->net_amount,
                    'employer' => $summary->employer_contributions,
                ])->all(),
                'controls' => $payslip->controls->map(fn ($control): array => ['code' => $control->code, 'label' => $control->label, 'status' => $control->status, 'difference' => $control->difference])->all(),
            ]);

        return response()->json([
            'data' => $batch,
            'progress' => ['processed' => $batch->processed_pages, 'total' => $batch->page_count ?: $batch->files->sum('page_count'), 'percent' => $batch->page_count ? round($batch->processed_pages * 100 / $batch->page_count, 1) : 0],
            'payslips' => $payslips,
        ]);
    }

    public function confirmBatch(Request $request, RemunerationPayslipBatch $batch): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_IMPORT_PERMISSION);
        $payload = Validator::make($request->all(), [
            'duplicate_action' => ['nullable', Rule::in(['reject', 'replace', 'new_version'])],
            'corrections' => ['nullable', 'array'],
            'corrections.*.payslip_id' => ['required', 'string', 'size:26'],
            'corrections.*.year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'corrections.*.month' => ['nullable', 'integer', 'between:1,12'],
            'corrections.*.staff_id' => ['nullable', 'integer', 'exists:staff,id'],
        ])->validate();
        abort_unless($batch->status === 'listo_revision', 422, 'El lote todavía no está listo para confirmar.');
        $batch->update(['status' => 'en_cola_confirmacion', 'stage' => 'confirmacion']);
        ConfirmPayslipBatch::dispatch($batch->id, $request->user()->id, $payload['corrections'] ?? [], $payload['duplicate_action'] ?? 'reject');

        return response()->json(['message' => 'La confirmación quedó en cola.', 'data' => $batch->fresh()], 202);
    }

    public function resolveIssue(Request $request, RemunerationPayslipIssue $issue): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_ISSUES_PERMISSION);
        $payload = Validator::make($request->all(), [
            'resolution' => ['required', 'string', 'min:5', 'max:1000'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'funding_source_id' => ['nullable', 'integer', 'exists:accounting_funding_sources,id'],
        ])->validate();

        DB::transaction(function () use ($issue, $payload, $request) {
            $issue->loadMissing(['batch', 'batch.school']);
            $payslip = RemunerationPayslip::query()->find($issue->payslip_id);
            if ($issue->code === 'unmatched_staff' && $payslip) {
                $staff = Staff::query()->find($payload['staff_id'] ?? null);
                abort_unless($staff && $this->normalizer->rut($staff->rut) === $this->normalizer->rut($payslip->rut_encrypted), 422, 'La asociación solo admite coincidencia exacta de RUT.');
                $payslip->update(['staff_id' => $staff->id]);
            }
            if ($issue->code === 'missing_period' && $payslip) {
                abort_unless(isset($payload['year'], $payload['month']), 422, 'Debe indicar mes y año.');
                $payslip->update(['year' => $payload['year'], 'month' => $payload['month']]);
            }
            if ($issue->code === 'unknown_funding_source') {
                $source = AccountingFundingSource::query()->findOrFail($payload['funding_source_id'] ?? 0);
                $label = (string) ($issue->context['label'] ?? '');
                abort_if($label === '', 422, 'La incidencia no contiene una etiqueta de subvención válida.');
                RemunerationFundingSourceAlias::query()->firstOrCreate([
                    'provider' => $payslip?->metadata['provider'] ?? '*',
                    'normalized_alias' => $label,
                ], [
                    'funding_source_id' => $source->id,
                    'alias' => $label,
                    'display_order' => 100,
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                RemunerationPayslipIssue::query()->firstOrCreate([
                    'batch_id' => $issue->batch_id,
                    'file_id' => $issue->file_id,
                    'page_id' => $issue->page_id,
                    'payslip_id' => $issue->payslip_id,
                    'code' => 'reprocess_required',
                    'status' => 'abierta',
                ], ['severity' => 'error', 'message' => 'El catálogo cambió; debe reprocesar el archivo para recalcular la distribución.']);
            }
            $issue->update([
                'status' => 'resuelta',
                'resolution' => $payload['resolution'],
                'resolved_at' => now(),
                'resolved_by' => $request->user()->id,
            ]);
        });
        $this->audit->log('resolver_incidencia_liquidacion_pdf', $issue, $request->user(), [], ['status' => 'resuelta'], $payload['resolution'], $request, ['issue_code' => $issue->code]);

        return response()->json(['message' => 'Incidencia resuelta con trazabilidad.', 'data' => $issue->fresh()]);
    }

    public function reprocess(Request $request, RemunerationPayslipFile $file): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_REPROCESS_PERMISSION);
        $batch = $this->imports->reprocess($file, $request->user());

        return response()->json(['message' => 'Se creó una nueva versión de procesamiento.', 'data' => $batch], 202);
    }

    public function annul(Request $request, RemunerationPayslipBatch $batch): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_ANNUL_PERMISSION);
        $payload = Validator::make($request->all(), ['reason' => ['required', 'string', 'min:5', 'max:1000']])->validate();
        DB::transaction(function () use ($batch) {
            $batch->update(['status' => 'anulado', 'stage' => 'resultado']);
            $batch->files()->update(['status' => 'anulado']);
            $batch->payslips()->update(['status' => 'anulada', 'is_current' => false]);
        });
        $this->audit->log('anular_importacion_liquidaciones_pdf', $batch, $request->user(), [], ['status' => 'anulado'], $payload['reason'], $request);

        return response()->json(['message' => 'La importación fue anulada sin eliminar su historial.']);
    }

    public function fileLink(Request $request, RemunerationPayslipFile $file): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);

        return response()->json(['url' => URL::temporarySignedRoute('remuneration.payslips.files.download', now()->addMinutes(5), ['file' => $file->id]), 'expires_in' => 300]);
    }

    public function downloadFile(Request $request, RemunerationPayslipFile $file): StreamedResponse|BinaryFileResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);
        abort_unless($request->hasValidSignature(), 403);
        $response = Storage::disk(config('remuneration_payslips.disk', 'local'))->download($file->private_path, $file->original_filename, ['Content-Type' => 'application/pdf']);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function history(Request $request): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);

        return response()->json($this->reporting->history($this->filters($request)));
    }

    public function matrix(Request $request, string $type): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);
        abort_unless(in_array($type, ['workers', 'earnings', 'discounts', 'contributions', 'controls'], true), 404);

        return response()->json($this->reporting->matrix($type, $this->filters($request)));
    }

    public function reconciliation(Request $request): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);
        $payload = Validator::make($request->all(), [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ])->validate();

        return response()->json($this->reporting->reconciliation(School::query()->findOrFail($payload['school_id']), $payload['year'], $payload['month']));
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_EXPORT_PERMISSION);
        $filters = $this->filters($request);

        return response()->streamDownload(function () use ($filters) {
            $spreadsheet = $this->exports->workbook($filters);
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'liquidaciones_sueldo_'.now()->format('Ymd_His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store, private']);
    }

    public function exportCsv(Request $request, string $type): StreamedResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_EXPORT_PERMISSION);
        abort_unless(in_array($type, ['earnings', 'discounts', 'contributions'], true), 404);
        $filters = $this->filters($request);

        return response()->streamDownload(function () use ($type, $filters) {
            $stream = fopen('php://output', 'wb');
            $this->exports->writeCsv($stream, $type, $filters);
            fclose($stream);
        }, $type.'_liquidaciones_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    public function generateProposal(Request $request): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_PROPOSAL_PERMISSION);
        $payload = Validator::make($request->all(), [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'period_id' => ['required', 'integer', 'exists:remuneration_periods,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        $proposal = $this->proposals->generate(School::query()->findOrFail($payload['school_id']), RemunerationPeriod::query()->findOrFail($payload['period_id']), $request->user(), $payload['notes'] ?? null);

        return response()->json(['message' => 'Propuesta revisable generada; no se contabilizó ningún movimiento.', 'data' => $proposal], 201);
    }

    public function proposals(Request $request): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_VIEW_PERMISSION);

        return response()->json(RemunerationPaymentProposal::query()
            ->with(['school:id,name,rbd', 'period:id,name,year,month'])
            ->withCount('items')
            ->latest('id')
            ->paginate(20));
    }

    public function confirmProposal(Request $request, RemunerationPaymentProposal $proposal): JsonResponse
    {
        $this->authorizePermission($request, RemunerationAccessService::PAYSLIP_PROPOSAL_PERMISSION);

        return response()->json(['message' => 'Propuesta confirmada sin contabilización automática.', 'data' => $this->proposals->confirm($proposal, $request->user())]);
    }

    /** @return array<string,mixed> */
    private function filters(Request $request): array
    {
        return Validator::make($request->query(), [
            'school_id' => ['nullable', 'integer', 'exists:lcd_schools,id'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'month_from' => ['nullable', 'integer', 'between:1,12'],
            'month_to' => ['nullable', 'integer', 'between:1,12'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'funding_source_id' => ['nullable', 'integer', 'exists:accounting_funding_sources,id'],
            'concept' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'reconciliation_status' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ])->validate();
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($this->access->canManage($request->user(), $permission), 403);
    }
}
