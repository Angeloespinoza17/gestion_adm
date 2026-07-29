<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingSubsidySettlement;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\AccountingSubsidyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingSubsidyController extends Controller
{
    public function __construct(
        private readonly AccountingAccessService $accessService,
        private readonly AccountingSubsidyService $subsidyService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizePermission($request, AccountingAccessService::FUNDING_PANEL_PERMISSION);
        $payload = $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
            'compare_period' => ['nullable', 'date_format:Y-m', 'different:period'],
        ]);

        return response()->json($this->subsidyService->dashboard(
            $payload['period'] ?? null,
            $payload['compare_period'] ?? null,
        ));
    }

    public function show(Request $request, AccountingSubsidySettlement $settlement): JsonResponse
    {
        $this->authorizePermission($request, AccountingAccessService::FUNDING_PANEL_PERMISSION);

        return response()->json(['data' => $this->subsidyService->loadSettlement($settlement)]);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorizePermission($request, AccountingAccessService::SUBSIDY_IMPORT_PERMISSION);
        $payload = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:15360'],
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        $result = $this->subsidyService->importFiles(
            $payload['files'],
            $request->user(),
            $payload['period'] ?? null,
        );

        return response()->json([
            'message' => count($result['duplicates']) > 0
                ? 'Importación completada con archivos duplicados omitidos.'
                : 'Importación procesada correctamente.',
            ...$result,
        ], 201);
    }

    public function manual(Request $request): JsonResponse
    {
        $this->authorizePermission($request, AccountingAccessService::SUBSIDY_IMPORT_PERMISSION);
        $payload = $request->validate([
            'rbd' => ['required', 'string', 'max:20'],
            'period' => ['required', 'date_format:Y-m'],
            'subsidy_type' => ['required', Rule::in([
                'normal',
                'sep_prioritario',
                'sep_preferente',
                'pro_retention',
                'school_bonus',
                'cd_brp',
                'cd_asignacion_tramo',
                'otro',
            ])],
            'funding_source_id' => ['nullable', 'integer', Rule::exists('accounting_funding_sources', 'id')->where('is_active', true)],
            'gross_amount' => ['required', 'numeric', 'gt:0'],
            'transferred_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'source_reference' => ['nullable', 'string', 'max:191'],
        ]);

        return response()->json([
            'message' => 'Liquidación manual creada correctamente.',
            'data' => $this->subsidyService->createManual($payload, $request->user()),
        ], 201);
    }

    public function approve(Request $request, AccountingSubsidySettlement $settlement): JsonResponse
    {
        $this->authorizePermission($request, AccountingAccessService::SUBSIDY_APPROVE_PERMISSION);
        $payload = $request->validate([
            'transferred_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json([
            'message' => 'Liquidación aprobada correctamente.',
            'data' => $this->subsidyService->approve(
                $settlement,
                $request->user(),
                array_key_exists('transferred_amount', $payload) ? (float) $payload['transferred_amount'] : null,
            ),
        ]);
    }

    public function post(Request $request, AccountingSubsidySettlement $settlement): JsonResponse
    {
        $this->authorizePermission($request, AccountingAccessService::SUBSIDY_POST_PERMISSION);
        $this->authorizePermission($request, AccountingAccessService::SUBSIDY_RECONCILE_PERMISSION);
        $payload = $request->validate([
            'received_at' => ['required', 'date'],
            'transferred_amount' => ['nullable', 'numeric', 'gt:0'],
            'manual_account_id' => [
                'required',
                'integer',
                Rule::exists('accounting_manual_accounts', 'id')->where('type', 'ingreso')->where('is_active', true),
            ],
            'bank_account_id' => ['required', 'integer', Rule::exists('accounting_bank_accounts', 'id')->where('is_active', true)],
            'cost_center_id' => ['nullable', 'integer', Rule::exists('accounting_cost_centers', 'id')->where('is_active', true)],
            'document_reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Liquidación conciliada y contabilizada correctamente.',
            'data' => $this->subsidyService->post($settlement, $payload, $request->user()),
        ]);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($this->accessService->canManage($request->user(), $permission), 403);
    }
}
