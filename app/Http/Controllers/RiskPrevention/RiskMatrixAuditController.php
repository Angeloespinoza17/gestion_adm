<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskAuditLog;
use App\Models\RiskPrevention\RiskControl;
use App\Models\RiskPrevention\RiskImportBatch;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskMatrixAuditController extends Controller
{
    public function __invoke(RiskMatrix $riskMatrix, Request $request): JsonResponse
    {
        $this->authorize('view', $riskMatrix);
        abort_unless($request->user()->hasPermission('risk-matrix.view-audit'), 403);
        $logs = RiskAuditLog::query()
            ->where('company_key', $riskMatrix->company_key)
            ->where(function ($query) use ($riskMatrix) {
                $query->where(fn ($q) => $q->where('auditable_type', $riskMatrix->getMorphClass())->where('auditable_id', $riskMatrix->id))
                    ->orWhere(fn ($q) => $q->where('auditable_type', (new RiskMatrixVersion)->getMorphClass())
                        ->whereIn('auditable_id', $riskMatrix->versions()->select('id')))
                    ->orWhere(fn ($q) => $q->where('auditable_type', (new RiskControl)->getMorphClass())
                        ->whereIn('auditable_id', DB::table('prevent_risk_controls as c')
                            ->join('prevent_risk_entries as r', 'r.id', '=', 'c.risk_entry_id')
                            ->join('prevent_risk_matrix_tasks as t', 't.id', '=', 'r.risk_matrix_task_id')
                            ->join('prevent_risk_matrix_processes as p', 'p.id', '=', 't.risk_matrix_process_id')
                            ->join('prevent_risk_matrix_versions as v', 'v.id', '=', 'p.risk_matrix_version_id')
                            ->where('v.risk_matrix_id', $riskMatrix->id)->select('c.id')))
                    ->orWhere(fn ($q) => $q->where('auditable_type', (new RiskImportBatch)->getMorphClass())
                        ->whereIn('auditable_id', RiskImportBatch::query()->where('target_matrix_id', $riskMatrix->id)->select('id')));
            })
            ->with('user:id,name')
            ->latest()->paginate(50);

        return response()->json($logs);
    }
}
