<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\SaveAccountingResourceRequest;
use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingBudget;
use App\Models\Accounting\AccountingExpense;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingJournalEntry;
use App\Models\Accounting\AccountingJournalEntryLine;
use App\Models\Accounting\AccountingManualAccount;
use App\Models\Accounting\AccountingManualVersion;
use App\Models\Accounting\AccountingPayable;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\AccountingDashboardService;
use App\Services\Accounting\AccountingJournalService;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\AccountingResourceRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingModuleController extends Controller
{
    public function __construct(
        private readonly AccountingAccessService $accessService,
        private readonly AccountingResourceRegistry $registry,
        private readonly AccountingDashboardService $dashboardService,
        private readonly AccountingReportService $reportService,
        private readonly AccountingJournalService $journalService,
        private readonly AccountingAuditService $auditService,
    ) {
    }

    public function catalogs(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canView($request->user()), 403);

        return response()->json([
            'statuses' => [
                'budgets' => ['borrador', 'en_revision', 'aprobado', 'cerrado'],
                'records' => ['borrador', 'pendiente_revision', 'observado', 'aprobado', 'rendido', 'rechazado'],
                'expenses' => ['borrador', 'pendiente_aprobacion', 'aprobado', 'pagado', 'anulado', 'rendido'],
                'incomes' => ['borrador', 'confirmado', 'conciliado', 'rendido', 'anulado'],
                'cash_funds' => ['abierto', 'pendiente_rendicion', 'rendido_parcialmente', 'rendido', 'observado', 'cerrado'],
                'bank_movements' => ['pendiente', 'conciliado', 'observado', 'diferencia', 'anulado'],
                'payables' => ['pendiente', 'programada', 'pagada_parcialmente', 'pagada', 'vencida', 'anulada'],
                'cheques' => ['disponible', 'emitido', 'entregado', 'cobrado', 'anulado', 'extraviado', 'vencido'],
                'f29' => ['pendiente', 'en_preparacion', 'presentado', 'pagado', 'rectificado', 'observado', 'no_aplica'],
                'declarations' => ['pendiente', 'en_preparacion', 'revisada', 'enviada', 'observada', 'rectificada', 'cerrada', 'presentada'],
                'journal' => ['borrador', 'registrado', 'anulado'],
            ],
            'types' => [
                'cost_centers' => ['operativo', 'academico', 'administrativo', 'programa', 'subvencion'],
                'funding_sources' => ['subvencion', 'aporte_municipal', 'ingreso_propio', 'convenio', 'otro'],
                'manual_accounts' => ['ingreso', 'egreso', 'activo', 'pasivo', 'patrimonio', 'orden'],
                'expense_documents' => ['factura', 'boleta_honorarios', 'boleta', 'comprobante', 'otro'],
                'payment_methods' => ['transferencia', 'cheque', 'efectivo', 'tarjeta', 'otro'],
                'fund_types' => ['caja_chica', 'fondo_por_rendir'],
                'party_types' => ['proveedor', 'beneficiario', 'cliente', 'arrendador', 'arrendatario', 'otro'],
            ],
            'data' => [
                'cost_centers' => \App\Models\Accounting\AccountingCostCenter::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'funding_sources' => \App\Models\Accounting\AccountingFundingSource::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'manual_versions' => \App\Models\Accounting\AccountingManualVersion::query()->orderByDesc('year')->get(['id', 'year', 'name', 'version', 'is_current']),
                'manual_accounts' => \App\Models\Accounting\AccountingManualAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'manual_version_id', 'code', 'name', 'type']),
                'budgets' => \App\Models\Accounting\AccountingBudget::query()->orderByDesc('year')->get(['id', 'year', 'name', 'status']),
                'parties' => \App\Models\Accounting\AccountingParty::query()->where('is_active', true)->orderBy('name')->get(['id', 'party_type', 'name', 'rut']),
                'bank_accounts' => \App\Models\Accounting\AccountingBankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(['id', 'bank_name', 'account_number']),
                'users' => \App\Models\User::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email']),
                'tax_periods' => \App\Models\Accounting\AccountingTaxPeriod::query()->orderByDesc('year')->orderByDesc('month')->get(['id', 'year', 'month', 'status']),
                'declaration_types' => \App\Models\Accounting\AccountingDeclarationType::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'category']),
            ],
            'permissions' => $request->user()?->permissionSlugs() ?? [],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewDashboard($request->user()), 403);

        return response()->json($this->dashboardService->build(
            $request->integer('year') ?: null,
            $request->integer('month') ?: null,
        ));
    }

    public function reports(Request $request): JsonResponse
    {
        abort_unless(
            $this->accessService->canManage($request->user(), AccountingAccessService::BALANCE_PERMISSION)
                || $this->accessService->canManage($request->user(), AccountingAccessService::EXPORT_PERMISSION),
            403
        );

        return response()->json($this->reportService->build($request->integer('year') ?: null));
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        abort_unless($this->accessService->canManage($request->user(), AccountingAccessService::EXPORT_PERMISSION), 403);

        $csv = $this->reportService->exportCsv($report, $request->integer('year') ?: null);
        $filename = sprintf('contabilidad-%s-%s.csv', $report, now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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

        $orderBy = $config['order_by'] ?? 'id';
        $orderDirection = in_array($orderBy, ['year', 'entry_date'], true) ? 'desc' : 'asc';
        $query->orderBy($orderBy, $orderDirection)->orderBy('id');

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

        return response()->json([
            'data' => $query->findOrFail($record),
        ]);
    }

    public function store(SaveAccountingResourceRequest $request, string $resource): JsonResponse
    {
        return $this->saveRecord($request, $resource);
    }

    public function update(SaveAccountingResourceRequest $request, string $resource, int $record): JsonResponse
    {
        return $this->saveRecord($request, $resource, $record);
    }

    public function destroy(Request $request, string $resource, int $record): JsonResponse
    {
        $config = $this->registry->get($resource);
        abort_unless($this->accessService->canManage($request->user(), $config['manage_permission']), 403);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($record);
        $oldValues = $model->getAttributes();

        $this->assertDeletionAllowed($resource, $model);

        DB::transaction(function () use ($request, $resource, $model, $oldValues) {
            $this->beforeDeletion($resource, $model);
            $model->delete();
            $this->afterDeletion($resource, $model);
            $this->auditService->log('eliminar', $model, $request->user(), $oldValues, [], 'Eliminación lógica de registro contable.', $request);
        });

        return response()->json(['message' => 'Registro eliminado correctamente.']);
    }

    private function saveRecord(SaveAccountingResourceRequest $request, string $resource, ?int $recordId = null): JsonResponse
    {
        $config = $this->registry->get($resource);
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        /** @var Model $model */
        $model = $recordId
            ? $modelClass::query()->findOrFail($recordId)
            : new $modelClass();

        $oldValues = $model->exists ? $model->getOriginal() : [];
        $payload = $request->validated();

        $this->assertBusinessRules($resource, $payload, $model);

        DB::transaction(function () use ($request, $resource, $model, $payload, $oldValues, $recordId) {
            if (!$model->exists && $this->hasColumn($model, 'created_by')) {
                $payload['created_by'] = $request->user()->id;
            } elseif ($this->hasColumn($model, 'created_by')) {
                $payload['created_by'] = $payload['created_by'] ?? $request->user()->id;
            }

            if ($this->hasColumn($model, 'updated_by')) {
                $payload['updated_by'] = $request->user()->id;
            }

            $model->fill($payload);
            $model->save();

            $this->afterSave($resource, $model, $payload);

            $fresh = $model->fresh($this->registry->get($resource)['with'] ?? []);
            $this->auditService->log(
                $recordId ? 'editar' : 'crear',
                $fresh,
                $request->user(),
                is_array($oldValues) ? $oldValues : [],
                $fresh?->getAttributes() ?? [],
                'Persistencia de registro contable.',
                $request
            );

            $model->setRelation('freshResult', $fresh);
        });

        return response()->json([
            'message' => $recordId ? 'Registro actualizado correctamente.' : 'Registro creado correctamente.',
            'data' => $model->getRelation('freshResult'),
        ], $recordId ? 200 : 201);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertBusinessRules(string $resource, array $payload, Model $model): void
    {
        if ($resource === 'budgets' && ($payload['status'] ?? null) === 'aprobado') {
            $lineCount = $model instanceof AccountingBudget ? $model->lines()->count() : 0;
            if ($lineCount === 0 && !$model->exists) {
                throw ValidationException::withMessages([
                    'status' => 'No se puede aprobar un presupuesto sin líneas asociadas.',
                ]);
            }
        }

        if ($resource === 'cash-funds' && ($payload['status'] ?? null) === 'cerrado' && (float) ($payload['current_balance'] ?? $model->current_balance ?? 0) !== 0.0) {
            throw ValidationException::withMessages([
                'current_balance' => 'No se puede cerrar un fondo con saldo pendiente sin justificación.',
            ]);
        }

        if ($resource === 'journal-entry-lines') {
            $debit = (float) ($payload['debit'] ?? 0);
            $credit = (float) ($payload['credit'] ?? 0);
            if ($debit <= 0 && $credit <= 0) {
                throw ValidationException::withMessages([
                    'debit' => 'Debe informar un débito o un crédito mayor a cero.',
                ]);
            }
        }
    }

    private function assertDeletionAllowed(string $resource, Model $model): void
    {
        if ($resource === 'manual-versions' && $model instanceof AccountingManualVersion) {
            $hasUsage = AccountingManualAccount::query()->where('manual_version_id', $model->id)->exists();

            if ($hasUsage) {
                throw ValidationException::withMessages([
                    'manual_version' => 'No se puede eliminar una versión de manual con cuentas o movimientos asociados.',
                ]);
            }
        }

        if ($resource === 'manual-accounts' && $model instanceof AccountingManualAccount) {
            $hasUsage = AccountingExpense::query()->where('manual_account_id', $model->id)->exists()
                || AccountingIncome::query()->where('manual_account_id', $model->id)->exists()
                || AccountingJournalEntryLine::query()->where('manual_account_id', $model->id)->exists();

            if ($hasUsage) {
                throw ValidationException::withMessages([
                    'manual_account' => 'No se puede eliminar una cuenta con movimientos asociados.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function afterSave(string $resource, Model $model, array $payload): void
    {
        if ($model instanceof AccountingIncome) {
            $this->journalService->syncForIncome($model->fresh(['manualAccount']));
            $this->recalculateBankBalance($model->bank_account_id);
        }

        if ($model instanceof AccountingExpense) {
            $this->journalService->syncForExpense($model->fresh(['manualAccount']));
            $this->recalculateBankBalance($model->bank_account_id);
        }

        if ($model instanceof AccountingJournalEntry && ($payload['status'] ?? null) !== 'borrador') {
            $this->journalService->assertBalanced(
                $model->lines()->get(['debit', 'credit'])->map(fn ($line) => $line->toArray())->all()
            );
        }

        if ($model instanceof AccountingJournalEntryLine) {
            $entry = $model->entry()->with('lines')->first();
            if ($entry && $entry->status !== 'borrador') {
                $this->journalService->assertBalanced($entry->lines->map(fn ($line) => $line->toArray())->all());
            }
        }

        if ($model instanceof AccountingPayable && $model->status === 'pagada') {
            $this->auditService->log('aprobar', $model, auth()->user(), [], $model->getAttributes(), 'Cuenta por pagar pagada.', request());
        }
    }

    private function beforeDeletion(string $resource, Model $model): void
    {
        if ($model instanceof AccountingIncome || $model instanceof AccountingExpense) {
            $this->journalService->deleteForSource($model);
        }
    }

    private function afterDeletion(string $resource, Model $model): void
    {
        if ($model instanceof AccountingIncome || $model instanceof AccountingExpense) {
            $this->recalculateBankBalance($model->bank_account_id);
        }
    }

    private function recalculateBankBalance(?int $bankAccountId): void
    {
        if (!$bankAccountId) {
            return;
        }

        $account = AccountingBankAccount::query()->find($bankAccountId);
        if (!$account) {
            return;
        }

        $incomeTotal = (float) AccountingIncome::query()->where('bank_account_id', $bankAccountId)->sum('amount');
        $expenseTotal = (float) AccountingExpense::query()->where('bank_account_id', $bankAccountId)->sum('total_amount');

        $account->update([
            'current_balance' => round($incomeTotal - $expenseTotal, 2),
        ]);
    }

    private function hasColumn(Model $model, string $column): bool
    {
        return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column);
    }
}
