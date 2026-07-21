<?php

namespace App\Http\Controllers\Remuneration;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingCostCenter;
use App\Models\Accounting\AccountingFundingSource;
use App\Models\Accounting\AccountingManualAccount;
use App\Models\Contract;
use App\Models\Remuneration\RemunerationBookAlertRule;
use App\Models\Remuneration\RemunerationBookConceptSetting;
use App\Models\Remuneration\RemunerationPayment;
use App\Models\Remuneration\RemunerationPayroll;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Staff;
use App\Services\Remuneration\PayrollAccountingService;
use App\Services\Remuneration\PayrollCalculationService;
use App\Services\Remuneration\RemunerationBookAnalyticsService;
use App\Services\Remuneration\RemunerationBookImportService;
use App\Services\Remuneration\RemunerationAccessService;
use App\Services\Remuneration\RemunerationAuditService;
use App\Services\Remuneration\RemunerationReportService;
use App\Services\Remuneration\RemunerationResourceRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RemunerationModuleController extends Controller
{
    public function __construct(
        private readonly RemunerationAccessService $accessService,
        private readonly RemunerationResourceRegistry $registry,
        private readonly PayrollCalculationService $calculationService,
        private readonly PayrollAccountingService $accountingService,
        private readonly RemunerationBookAnalyticsService $bookAnalyticsService,
        private readonly RemunerationBookImportService $bookImportService,
        private readonly RemunerationReportService $reportService,
        private readonly RemunerationAuditService $auditService,
    ) {
    }

    public function catalogs(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canView($request->user()), 403);

        return response()->json([
            'statuses' => [
                'periods' => ['abierto', 'en_calculo', 'cerrado', 'reabierto'],
                'movements' => ['borrador', 'aprobado', 'ejecutado', 'anulado'],
                'payrolls' => ['calculada', 'observada', 'aprobada', 'pagada', 'anulada'],
                'payments' => ['pendiente', 'pagado', 'anulado'],
                'accounting_exports' => ['generado', 'reversado'],
                'medical_leaves' => ['ingresada', 'enviada', 'aprobada', 'rechazada', 'liquidada', 'anulada'],
                'documents' => ['vigente', 'por_vencer', 'vencido', 'pendiente', 'observado', 'archivado'],
                'onboarding' => ['pendiente', 'en_proceso', 'completo', 'observado', 'anulado'],
                'climate' => ['borrador', 'abierta', 'cerrada', 'reportada', 'plan_accion'],
                'action_plans' => ['pendiente', 'en_proceso', 'completo', 'atrasado', 'cancelado'],
                'workload' => ['vigente', 'planificada', 'reemplazo', 'cerrada', 'anulada'],
                'cv_bank' => ['postulante', 'preseleccionado', 'entrevistado', 'descartado', 'contratado', 'archivado'],
                'replacement_pool' => ['disponible', 'ocupado', 'no_disponible', 'observado', 'archivado'],
                'job_profiles' => ['borrador', 'vigente', 'en_revision', 'obsoleto'],
                'certificates' => ['solicitado', 'emitido', 'entregado', 'anulado'],
                'permission_requests' => collect(\App\Models\PermissionRequest::STATUS_OPTIONS)->pluck('value')->all(),
            ],
            'types' => [
                'employee_types' => ['docente', 'asistente_educacion', 'administrativo', 'directivo', 'reemplazo'],
                'concept_types' => ['earning', 'deduction', 'employer_contribution'],
                'calculation_types' => ['manual', 'fixed', 'formula'],
                'movement_types' => ['earning', 'deduction', 'license', 'permission', 'delay', 'absence', 'replacement', 'overtime', 'adjustment', 'finiquito'],
                'payment_methods' => ['transferencia', 'cheque', 'efectivo', 'vale_vista', 'otro'],
                'units' => ['clp', 'uf', 'utm', 'percent', 'factor'],
                'document_types' => ['contrato', 'anexo', 'certificado', 'licencia_medica', 'titulo', 'antecedentes', 'capacitacion', 'entrega_materiales', 'otro'],
                'certificate_types' => ['antiguedad', 'renta', 'funciones', 'jornada', 'relacion_laboral', 'otro'],
                'risk_levels' => ['bajo', 'medio', 'alto', 'critico'],
                'workload_roles' => ['aula', 'coordinacion', 'pie', 'sep', 'administrativo', 'reemplazo', 'jefatura', 'apoyo'],
                'attendance_statuses' => collect(\App\Models\PermissionRequest::ATTENDANCE_STATUS_OPTIONS)->pluck('value')->all(),
                'payroll_statuses' => collect(\App\Models\PermissionRequest::PAYROLL_STATUS_OPTIONS)->pluck('value')->all(),
            ],
            'data' => [
                'periods' => RemunerationPeriod::query()->orderByDesc('year')->orderByDesc('month')->get(['id', 'year', 'month', 'name', 'status']),
                'staff' => Staff::query()->where('active', true)->orderBy('full_name')->get(['id', 'full_name', 'rut', 'cargo_id']),
                'contracts' => Contract::query()->with('staff:id,full_name,rut')->orderByDesc('start_date')->get(['id', 'staff_id', 'position_name', 'contract_type', 'start_date', 'end_date', 'status']),
                'users' => \App\Models\User::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email']),
                'cargos' => \App\Models\Cargo::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slug']),
                'departments' => \App\Models\Department::query()->orderBy('name')->get(['id', 'name']),
                'permission_types' => \App\Models\PermissionType::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
                'cost_centers' => AccountingCostCenter::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'funding_sources' => AccountingFundingSource::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'manual_accounts' => AccountingManualAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
                'bank_accounts' => AccountingBankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(['id', 'bank_name', 'account_name', 'account_number']),
                'concepts' => \App\Models\Remuneration\RemunerationConcept::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'type', 'is_imponible', 'is_taxable']),
                'job_profiles' => \App\Models\HumanResources\HrJobProfile::query()->orderBy('title')->get(['id', 'code', 'title', 'status']),
                'document_controls' => \App\Models\HumanResources\HrDocumentControl::query()->orderByDesc('id')->limit(200)->get(['id', 'staff_id', 'title', 'document_type', 'status']),
                'climate_surveys' => \App\Models\HumanResources\HrClimateSurvey::query()->orderByDesc('id')->get(['id', 'title', 'scope', 'status', 'risk_level']),
                'cv_bank_entries' => \App\Models\HumanResources\HrCvBankEntry::query()->orderBy('full_name')->get(['id', 'full_name', 'desired_position', 'specialty', 'status']),
            ],
            'permissions' => $request->user()?->permissionSlugs() ?? [],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewDashboard($request->user()), 403);

        return response()->json($this->reportService->dashboard(
            $request->integer('year') ?: null,
            $request->integer('month') ?: null,
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::EXPORT_PERMISSION), 403);

        $csv = $this->reportService->exportCsv($request->integer('period_id') ?: null);
        $filename = 'remuneraciones-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function bookAnalytics(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::REPORTS_PERMISSION), 403);

        $payload = Validator::make($request->all(), [
            'period_id' => ['nullable', 'integer', 'exists:remuneration_periods,id'],
            'from_period_id' => ['nullable', 'integer', 'exists:remuneration_periods,id'],
            'to_period_id' => ['nullable', 'integer', 'exists:remuneration_periods,id'],
            'import_id' => ['nullable', 'integer', 'exists:remuneration_book_imports,id'],
            'employee_type' => ['nullable', 'string', 'max:120'],
            'concept_key' => ['nullable', 'string', 'size:40'],
        ])->validate();

        return response()->json($this->bookAnalyticsService->dashboard($payload));
    }

    public function updateBookConceptSetting(Request $request, string $conceptKey): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::CONCEPTS_PERMISSION), 403);

        Validator::make(['concept_key' => $conceptKey], [
            'concept_key' => ['required', 'string', 'size:40'],
        ])->validate();

        $payload = Validator::make($request->all(), [
            'code' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'nature' => ['required', 'string', 'in:Haber'],
            'group' => ['nullable', 'string', 'max:120'],
            'is_union_income' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $setting = RemunerationBookConceptSetting::query()->firstOrNew(['concept_key' => $conceptKey]);
        $setting->fill([
            'code' => $payload['code'] ?? null,
            'name' => $payload['name'],
            'label' => ($payload['label'] ?? null) ?: trim((($payload['code'] ?? '') ? '(' . $payload['code'] . ') ' : '') . $payload['name']),
            'nature' => $payload['nature'],
            'group' => $payload['group'] ?? null,
            'is_union_income' => (bool) $payload['is_union_income'],
            'notes' => $payload['notes'] ?? null,
            'last_seen_at' => now(),
            'updated_by' => $request->user()?->id,
        ]);

        if (!$setting->exists) {
            $setting->created_by = $request->user()?->id;
        }

        $setting->save();

        return response()->json([
            'message' => $setting->is_union_income ? 'Haber marcado como sindical.' : 'Haber desmarcado como sindical.',
            'data' => $setting,
        ]);
    }

    public function bookAlertRules(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::REPORTS_PERMISSION), 403);

        return response()->json([
            'data' => RemunerationBookAlertRule::query()
                ->orderByDesc('enabled')
                ->orderBy('name')
                ->get()
                ->map(fn (RemunerationBookAlertRule $rule) => $this->bookAlertRulePayload($rule))
                ->values(),
        ]);
    }

    public function storeBookAlertRule(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::REPORTS_PERMISSION), 403);

        $payload = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1200'],
            'severity' => ['required', 'string', 'in:critica,requiere_revision,informativa'],
            'metric' => ['required', 'string', 'in:gross_total,net_amount,total_deductions,legal_deductions,other_deductions,deduction_rate,worked_days,weekly_hours,concept_amount'],
            'operator' => ['required', 'string', 'in:gt,gte,lt,lte,eq,neq'],
            'threshold_value' => ['required', 'numeric'],
            'concept_key' => ['nullable', 'required_if:metric,concept_amount', 'string', 'size:40'],
            'concept_label' => ['nullable', 'string', 'max:255'],
            'employee_type' => ['nullable', 'string', 'max:120'],
            'enabled' => ['nullable', 'boolean'],
        ])->validate();

        $rule = RemunerationBookAlertRule::query()->create([
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'severity' => $payload['severity'],
            'metric' => $payload['metric'],
            'operator' => $payload['operator'],
            'threshold_value' => $payload['threshold_value'],
            'concept_key' => $payload['concept_key'] ?? null,
            'concept_label' => $payload['concept_label'] ?? null,
            'employee_type' => $payload['employee_type'] ?? null,
            'enabled' => (bool) ($payload['enabled'] ?? true),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Regla de alerta creada.',
            'data' => $this->bookAlertRulePayload($rule),
        ], 201);
    }

    private function bookAlertRulePayload(RemunerationBookAlertRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'severity' => $rule->severity,
            'metric' => $rule->metric,
            'operator' => $rule->operator,
            'threshold_value' => (float) $rule->threshold_value,
            'concept_key' => $rule->concept_key,
            'concept_label' => $rule->concept_label,
            'employee_type' => $rule->employee_type,
            'enabled' => (bool) $rule->enabled,
            'created_at' => $rule->created_at?->format('Y-m-d H:i'),
            'updated_at' => $rule->updated_at?->format('Y-m-d H:i'),
        ];
    }

    public function previewImport(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::IMPORT_PERMISSION), 403);

        $payload = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,zip', 'max:20480'],
        ])->validate();

        return response()->json($this->bookImportService->preview($payload['file']));
    }

    public function importBook(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::IMPORT_PERMISSION), 403);

        $payload = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,zip', 'max:20480'],
            'replace' => ['nullable', 'boolean'],
        ])->validate();

        $import = $this->bookImportService->import(
            $payload['file'],
            $request->user(),
            (bool) ($payload['replace'] ?? false),
        );

        return response()->json([
            'message' => 'Libro de remuneraciones importado correctamente.',
            'data' => $import,
        ], 201);
    }

    public function payrollPdfData(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::EXPORT_PERMISSION), 403);

        $payload = Validator::make($request->all(), [
            'payroll_id' => ['nullable', 'integer', 'exists:remuneration_payrolls,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'period_id' => ['nullable', 'integer', 'exists:remuneration_periods,id'],
            'from_period_id' => ['nullable', 'integer', 'exists:remuneration_periods,id'],
            'to_period_id' => ['nullable', 'integer', 'exists:remuneration_periods,id'],
            'payroll_type' => ['nullable', 'string', 'max:40'],
            'include_annulled' => ['nullable', 'boolean'],
        ])->validate();

        $query = RemunerationPayroll::query()
            ->with([
                'period:id,name,year,month,period_start,period_end,status',
                'staff:id,full_name,rut,birth_date,start_date,contract_type,contract_hours,cargo_id',
                'staff.cargo:id,name',
                'contract:id,staff_id,position_name,contract_type,start_date,end_date,status',
                'employeeProfile',
                'lines',
                'distributions.fundingSource:id,code,name',
                'distributions.costCenter:id,code,name',
                'payments:id,payroll_id,payment_date,amount,payment_method,reference,status',
            ]);

        if (!empty($payload['payroll_id'])) {
            $query->whereKey($payload['payroll_id']);
        }

        if (!empty($payload['staff_id'])) {
            $query->where('staff_id', $payload['staff_id']);
        }

        if (!empty($payload['period_id'])) {
            $query->where('period_id', $payload['period_id']);
        }

        if (!empty($payload['payroll_type'])) {
            $query->where('payroll_type', $payload['payroll_type']);
        }

        if (empty($payload['include_annulled'])) {
            $query->where('status', '!=', 'anulada');
        }

        $fromPeriod = !empty($payload['from_period_id']) ? RemunerationPeriod::query()->find($payload['from_period_id']) : null;
        $toPeriod = !empty($payload['to_period_id']) ? RemunerationPeriod::query()->find($payload['to_period_id']) : null;

        if ($fromPeriod || $toPeriod) {
            $query->whereHas('period', function (Builder $builder) use ($fromPeriod, $toPeriod) {
                if ($fromPeriod) {
                    $builder->where(function (Builder $periodQuery) use ($fromPeriod) {
                        $periodQuery
                            ->where('year', '>', $fromPeriod->year)
                            ->orWhere(function (Builder $sameYear) use ($fromPeriod) {
                                $sameYear->where('year', $fromPeriod->year)->where('month', '>=', $fromPeriod->month);
                            });
                    });
                }

                if ($toPeriod) {
                    $builder->where(function (Builder $periodQuery) use ($toPeriod) {
                        $periodQuery
                            ->where('year', '<', $toPeriod->year)
                            ->orWhere(function (Builder $sameYear) use ($toPeriod) {
                                $sameYear->where('year', $toPeriod->year)->where('month', '<=', $toPeriod->month);
                            });
                    });
                }
            });
        }

        $payrolls = $query
            ->limit(500)
            ->get()
            ->sortBy(fn (RemunerationPayroll $payroll) => sprintf(
                '%04d%02d-%s-%08d',
                $payroll->period?->year ?? 0,
                $payroll->period?->month ?? 0,
                $payroll->staff?->full_name ?? '',
                $payroll->id
            ))
            ->values();

        if ($payrolls->isEmpty()) {
            throw ValidationException::withMessages([
                'payrolls' => 'No se encontraron liquidaciones para exportar.',
            ]);
        }

        $this->auditService->log(
            'exportar_pdf_liquidaciones',
            $payrolls->first(),
            $request->user(),
            [],
            [],
            'Exportación de liquidaciones a PDF desde snapshot histórico.',
            $request,
            ['count' => $payrolls->count()]
        );

        return response()->json([
            'generated_at' => now()->format('Y-m-d H:i'),
            'institution' => config('app.name', 'Colegio'),
            'historical_snapshot' => true,
            'data' => $payrolls,
        ]);
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $config = $this->registry->get($resource);
        abort_unless($this->accessService->canManage($request->user(), $config['view_permission']), 403);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        /** @var Builder $query */
        $query = $modelClass::query();

        if (!empty($config['with'])) {
            $query->with($config['with']);
        }

        $search = trim((string) $request->query('search'));
        if ($search !== '' && !empty($config['search'])) {
            $query->where(function (Builder $builder) use ($config, $search) {
                foreach ($config['search'] as $field) {
                    $builder->orWhere($field, 'like', '%' . $search . '%');
                }
            });
        }

        foreach (($config['filters'] ?? []) as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        $query->orderBy($config['order_by'] ?? 'id', $config['order_direction'] ?? 'asc')->orderBy('id');

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20)));
    }

    public function show(Request $request, string $resource, int $record): JsonResponse
    {
        $config = $this->registry->get($resource);
        abort_unless($this->accessService->canManage($request->user(), $config['view_permission']), 403);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query();
        if (!empty($config['with'])) {
            $query->with($config['with']);
        }

        return response()->json(['data' => $query->findOrFail($record)]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        return $this->saveRecord($request, $resource);
    }

    public function update(Request $request, string $resource, int $record): JsonResponse
    {
        return $this->saveRecord($request, $resource, $record);
    }

    public function destroy(Request $request, string $resource, int $record): JsonResponse
    {
        $config = $this->registry->get($resource);
        abort_unless($this->accessService->canManage($request->user(), $config['manage_permission']), 403);
        abort_if($config['read_only'] ?? false, 422, 'Este recurso es solo de consulta.');
        abort_if(in_array($resource, ['audit-logs', 'accounting-exports'], true), 422, 'Este recurso no permite eliminación directa.');

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $model = $modelClass::query()->findOrFail($record);
        $this->assertOpenPeriodForModel($model);
        $oldValues = $model->getAttributes();

        $model->delete();

        $this->auditService->log('eliminar', $model, $request->user(), $oldValues, [], 'Eliminación de registro de remuneraciones.', $request);

        return response()->json(['message' => 'Registro eliminado correctamente.']);
    }

    public function calculate(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::CALCULATE_PERMISSION), 403);

        $payload = Validator::make($request->all(), [
            'period_id' => ['required', 'integer', 'exists:remuneration_periods,id'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'payroll_type' => ['nullable', 'string', 'max:40'],
            'force' => ['nullable', 'boolean'],
        ])->validate();

        $payroll = $this->calculationService->calculate(
            RemunerationPeriod::query()->findOrFail($payload['period_id']),
            Staff::query()->findOrFail($payload['staff_id']),
            $request->user(),
            $payload,
        );

        return response()->json(['message' => 'Liquidación calculada correctamente.', 'data' => $payroll]);
    }

    public function bulkCalculate(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::CALCULATE_PERMISSION), 403);

        $payload = Validator::make($request->all(), [
            'period_id' => ['required', 'integer', 'exists:remuneration_periods,id'],
            'staff_ids' => ['required', 'array', 'min:1'],
            'staff_ids.*' => ['integer', 'exists:staff,id'],
            'payroll_type' => ['nullable', 'string', 'max:40'],
            'force' => ['nullable', 'boolean'],
        ])->validate();

        $period = RemunerationPeriod::query()->findOrFail($payload['period_id']);
        $results = [];
        foreach ($payload['staff_ids'] as $staffId) {
            $results[] = $this->calculationService->calculate(
                $period,
                Staff::query()->findOrFail($staffId),
                $request->user(),
                $payload,
            );
        }

        return response()->json(['message' => 'Liquidaciones calculadas correctamente.', 'data' => $results]);
    }

    public function approve(Request $request, RemunerationPayroll $payroll): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::APPROVE_PERMISSION), 403);
        $this->assertPayrollPeriodOpen($payroll);

        $oldValues = $payroll->getOriginal();
        $payroll->fill([
            'status' => 'aprobada',
            'approved_at' => now(),
            'approved_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
            'observations' => $request->input('observations', $payroll->observations),
        ])->save();

        $this->auditService->log('aprobar', $payroll, $request->user(), $oldValues, $payroll->getAttributes(), 'Aprobación de liquidación.', $request);

        return response()->json(['message' => 'Liquidación aprobada.', 'data' => $payroll->fresh(['lines', 'distributions'])]);
    }

    public function observe(Request $request, RemunerationPayroll $payroll): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::APPROVE_PERMISSION), 403);
        $this->assertPayrollPeriodOpen($payroll);

        $payload = Validator::make($request->all(), [
            'observations' => ['required', 'string'],
        ])->validate();

        $oldValues = $payroll->getOriginal();
        $payroll->fill([
            'status' => 'observada',
            'observations' => $payload['observations'],
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->auditService->log('observar', $payroll, $request->user(), $oldValues, $payroll->getAttributes(), 'Observación de liquidación.', $request);

        return response()->json(['message' => 'Liquidación observada.', 'data' => $payroll->fresh(['lines', 'distributions'])]);
    }

    public function annul(Request $request, RemunerationPayroll $payroll): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::APPROVE_PERMISSION), 403);
        $this->assertPayrollPeriodOpen($payroll);

        $oldValues = $payroll->getOriginal();
        $payroll->fill([
            'status' => 'anulada',
            'annulled_at' => now(),
            'annulled_by' => $request->user()?->id,
            'observations' => $request->input('observations', $payroll->observations),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->auditService->log('anular', $payroll, $request->user(), $oldValues, $payroll->getAttributes(), 'Anulación de liquidación.', $request);

        return response()->json(['message' => 'Liquidación anulada.', 'data' => $payroll->fresh(['lines', 'distributions'])]);
    }

    public function pay(Request $request, RemunerationPayroll $payroll): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::PAYMENTS_PERMISSION), 403);
        $this->assertPayrollPeriodOpen($payroll);

        if (!in_array($payroll->status, ['aprobada', 'pagada'], true)) {
            throw ValidationException::withMessages(['payroll' => 'Solo se pueden pagar liquidaciones aprobadas.']);
        }

        $payload = Validator::make($request->all(), [
            'payment_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'bank_account_id' => ['nullable', 'integer', 'exists:accounting_bank_accounts,id'],
            'reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $result = DB::transaction(function () use ($request, $payroll, $payload) {
            $payment = RemunerationPayment::query()->create([
                'payroll_id' => $payroll->id,
                'payment_date' => $payload['payment_date'] ?? now()->toDateString(),
                'amount' => $payload['amount'] ?? $payroll->net_amount,
                'payment_method' => $payload['payment_method'] ?? 'transferencia',
                'bank_account_id' => $payload['bank_account_id'] ?? null,
                'reference' => $payload['reference'] ?? null,
                'status' => 'pagado',
                'paid_at' => now(),
                'paid_by' => $request->user()?->id,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            $oldValues = $payroll->getOriginal();
            $paid = (int) $payroll->payments()->sum('amount');
            if ($paid >= (int) $payroll->net_amount) {
                $payroll->fill([
                    'status' => 'pagada',
                    'paid_at' => now(),
                    'paid_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ])->save();
            }

            $this->auditService->log('pagar', $payroll, $request->user(), $oldValues, $payroll->getAttributes(), 'Registro de pago de liquidación.', $request, ['payment_id' => $payment->id]);

            return $payroll->fresh(['payments', 'lines', 'distributions']);
        });

        return response()->json(['message' => 'Pago registrado.', 'data' => $result]);
    }

    public function centralize(Request $request, RemunerationPayroll $payroll): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::ACCOUNTING_PERMISSION), 403);

        $export = $this->accountingService->centralize($payroll, $request->user());

        return response()->json(['message' => 'Centralización contable generada.', 'data' => $export]);
    }

    public function closePeriod(Request $request, RemunerationPeriod $period): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::CLOSE_PERIOD_PERMISSION), 403);

        $pending = $period->payrolls()->whereIn('status', ['calculada', 'observada'])->count();
        if ($pending > 0) {
            throw ValidationException::withMessages([
                'period' => 'No se puede cerrar un período con liquidaciones calculadas u observadas pendientes de aprobación.',
            ]);
        }

        $oldValues = $period->getOriginal();
        $period->fill([
            'status' => 'cerrado',
            'closed_at' => now(),
            'closed_by' => $request->user()?->id,
            'notes' => $request->input('notes', $period->notes),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->auditService->log('cerrar_periodo', $period, $request->user(), $oldValues, $period->getAttributes(), 'Cierre de período de remuneraciones.', $request);

        return response()->json(['message' => 'Período cerrado.', 'data' => $period]);
    }

    public function reopenPeriod(Request $request, RemunerationPeriod $period): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), RemunerationAccessService::CLOSE_PERIOD_PERMISSION), 403);

        $oldValues = $period->getOriginal();
        $period->fill([
            'status' => 'reabierto',
            'reopened_at' => now(),
            'reopened_by' => $request->user()?->id,
            'notes' => $request->input('notes', $period->notes),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->auditService->log('reabrir_periodo', $period, $request->user(), $oldValues, $period->getAttributes(), 'Reapertura de período de remuneraciones.', $request);

        return response()->json(['message' => 'Período reabierto.', 'data' => $period]);
    }

    private function saveRecord(Request $request, string $resource, ?int $recordId = null): JsonResponse
    {
        $config = $this->registry->get($resource);
        abort_unless($this->accessService->canManage($request->user(), $config['manage_permission']), 403);
        abort_if($config['read_only'] ?? false, 422, 'Este recurso es solo de consulta.');
        abort_if(in_array($resource, ['audit-logs', 'accounting-exports'], true), 422, 'Este recurso no permite edición directa.');

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $model = $recordId ? $modelClass::query()->findOrFail($recordId) : new $modelClass();
        $rules = $config['rules']($recordId);
        $input = $request->all();

        if (in_array($resource, ['departments', 'functions'], true) && empty($input['slug']) && !empty($input['name'])) {
            $input['slug'] = Str::slug((string) $input['name']);
        }

        $payload = Validator::make($input, $rules)->validate();

        $this->assertOpenPeriodForPayload($resource, $payload, $model);

        $oldValues = $model->exists ? $model->getOriginal() : [];
        if (!$model->exists && $this->hasColumn($model, 'created_by')) {
            $payload['created_by'] = $request->user()?->id;
        }
        if ($this->hasColumn($model, 'updated_by')) {
            $payload['updated_by'] = $request->user()?->id;
        }

        $model->fill($payload);
        $model->save();

        $fresh = $model->fresh($config['with'] ?? []);
        $this->auditService->log(
            $recordId ? 'editar' : 'crear',
            $fresh,
            $request->user(),
            $oldValues,
            $fresh?->getAttributes() ?? [],
            'Persistencia de registro de remuneraciones.',
            $request,
            ['resource' => $resource]
        );

        return response()->json([
            'message' => $recordId ? 'Registro actualizado correctamente.' : 'Registro creado correctamente.',
            'data' => $fresh,
        ], $recordId ? 200 : 201);
    }

    private function assertOpenPeriodForPayload(string $resource, array $payload, Model $model): void
    {
        if ($resource === 'periods') {
            return;
        }

        $periodId = $payload['period_id'] ?? ($model->period_id ?? null);
        if (!$periodId && $model instanceof RemunerationPayment) {
            $periodId = $model->payroll?->period_id;
        }
        if (!$periodId) {
            return;
        }

        $period = RemunerationPeriod::query()->find($periodId);
        if ($period?->isClosed()) {
            throw ValidationException::withMessages([
                'period_id' => 'No se puede modificar un registro asociado a un período cerrado.',
            ]);
        }
    }

    private function assertOpenPeriodForModel(Model $model): void
    {
        $periodId = $model->period_id ?? null;
        if (!$periodId && $model instanceof RemunerationPayment) {
            $periodId = $model->payroll?->period_id;
        }

        if ($periodId && RemunerationPeriod::query()->find($periodId)?->isClosed()) {
            throw ValidationException::withMessages([
                'period_id' => 'No se puede eliminar un registro asociado a un período cerrado.',
            ]);
        }
    }

    private function assertPayrollPeriodOpen(RemunerationPayroll $payroll): void
    {
        $payroll->loadMissing('period');
        if ($payroll->period?->isClosed()) {
            throw ValidationException::withMessages([
                'period_id' => 'No se puede modificar una liquidación de un período cerrado.',
            ]);
        }
    }

    private function hasColumn(Model $model, string $column): bool
    {
        return Schema::hasColumn($model->getTable(), $column);
    }
}
