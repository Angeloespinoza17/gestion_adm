<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\AccountingBudgetExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingBudgetExecutionController extends Controller
{
    public function __construct(
        private readonly AccountingAccessService $accessService,
        private readonly AccountingBudgetExecutionService $service,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), AccountingAccessService::BUDGET_EXECUTION_VIEW_PERMISSION), 403);
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        return response()->json($this->service->dashboard(isset($validated['year']) ? (int) $validated['year'] : null));
    }

    public function import(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManage($request->user(), AccountingAccessService::BUDGET_EXECUTION_IMPORT_PERMISSION), 403);
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'file' => [
                'required',
                'file',
                'max:20480',
                'extensions:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
            ],
        ]);

        return response()->json(
            $this->service->import($validated['file'], (int) $validated['year'], $request->user(), $request),
            201,
        );
    }
}
