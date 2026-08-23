<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\StoreRiskMatrixRequest;
use App\Http\Resources\RiskPrevention\RiskMatrixResource;
use App\Models\RiskPrevention\RiskEvidence;
use App\Models\RiskPrevention\RiskMatrix;
use App\Services\RiskPrevention\RiskMatrixAuditService;
use App\Services\RiskPrevention\RiskMatrixService;
use App\Services\RiskPrevention\RiskMatrixStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RiskMatrixController extends Controller
{
    public function __construct(
        private readonly RiskMatrixService $matrices,
        private readonly RiskMatrixStructureService $structures,
        private readonly RiskMatrixAuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', RiskMatrix::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:30'],
            'work_center_id' => ['nullable', 'integer'], 'year' => ['nullable', 'integer', 'between:2000,2200'],
            'risk_level' => ['nullable', 'string', 'max:40'], 'overdue_controls' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:code,name,created_at'], 'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $currentVersionIdSql = 'COALESCE(prevent_risk_matrices.active_version_id, '
            .'(SELECT latest_version.id FROM prevent_risk_matrix_versions AS latest_version '
            .'WHERE latest_version.risk_matrix_id = prevent_risk_matrices.id '
            .'ORDER BY latest_version.version_number DESC LIMIT 1))';
        $query = RiskMatrix::query()
            ->where('company_key', config('risk_matrix.company.key'))
            ->with([
                'workCenter:id,name,code',
                'activeVersion.programResponsible:id,name',
                'activeVersion.program:id,risk_matrix_version_id,progress_percentage',
                'latestVersion.programResponsible:id,name',
                'latestVersion.program:id,risk_matrix_version_id,progress_percentage',
            ]);
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('folio', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }
        if ($status = $filters['status'] ?? null) {
            $query->whereHas('versions', fn ($q) => $q
                ->whereColumn('prevent_risk_matrix_versions.id', DB::raw($currentVersionIdSql))
                ->where('status', $status));
        }
        if ($workCenter = $filters['work_center_id'] ?? null) {
            $query->where('work_center_id', $workCenter);
        }
        if ($year = $filters['year'] ?? null) {
            $query->whereHas('versions', fn ($q) => $q
                ->whereColumn('prevent_risk_matrix_versions.id', DB::raw($currentVersionIdSql))
                ->whereYear('prepared_on', $year));
        }
        if ($level = $filters['risk_level'] ?? null) {
            $query->whereExists(fn ($q) => $q->selectRaw(1)->from('prevent_risk_assessments as fa')
                ->join('prevent_risk_catalog_items as fl', 'fl.id', '=', 'fa.calculated_level_id')
                ->join('prevent_risk_entries as fr', 'fr.id', '=', 'fa.risk_entry_id')
                ->join('prevent_risk_matrix_tasks as ft', 'ft.id', '=', 'fr.risk_matrix_task_id')
                ->join('prevent_risk_matrix_processes as fp', 'fp.id', '=', 'ft.risk_matrix_process_id')
                ->whereColumn('fp.risk_matrix_version_id', DB::raw($currentVersionIdSql))->where('fa.active', true)->where('fa.phase', 'current')->where('fl.code', $level));
        }
        if ($request->boolean('overdue_controls')) {
            $query->whereExists(fn ($q) => $q->selectRaw(1)->from('prevent_risk_controls as fc')
                ->join('prevent_risk_entries as fr', 'fr.id', '=', 'fc.risk_entry_id')
                ->join('prevent_risk_matrix_tasks as ft', 'ft.id', '=', 'fr.risk_matrix_task_id')
                ->join('prevent_risk_matrix_processes as fp', 'fp.id', '=', 'ft.risk_matrix_process_id')
                ->whereColumn('fp.risk_matrix_version_id', DB::raw($currentVersionIdSql))->whereDate('fc.due_date', '<', now())->whereNotIn('fc.status', ['verified', 'cancelled']));
        }

        $riskSubquery = fn () => DB::table('prevent_risk_entries as r')->join('prevent_risk_matrix_tasks as t', 't.id', '=', 'r.risk_matrix_task_id')->join('prevent_risk_matrix_processes as p', 'p.id', '=', 't.risk_matrix_process_id')->selectRaw('COUNT(*)')->whereColumn('p.risk_matrix_version_id', DB::raw($currentVersionIdSql));
        $levelSubquery = fn (string $code) => DB::table('prevent_risk_assessments as a')->join('prevent_risk_catalog_items as l', 'l.id', '=', 'a.calculated_level_id')->join('prevent_risk_entries as r', 'r.id', '=', 'a.risk_entry_id')->join('prevent_risk_matrix_tasks as t', 't.id', '=', 'r.risk_matrix_task_id')->join('prevent_risk_matrix_processes as p', 'p.id', '=', 't.risk_matrix_process_id')->selectRaw('COUNT(*)')->whereColumn('p.risk_matrix_version_id', DB::raw($currentVersionIdSql))->where('a.active', true)->where('a.phase', 'current')->where('l.code', $code);
        $query->addSelect([
            'risk_count' => $riskSubquery(), 'important_count' => $levelSubquery('important'), 'intolerable_count' => $levelSubquery('intolerable'),
            'overdue_controls_count' => DB::table('prevent_risk_controls as c')->join('prevent_risk_entries as r', 'r.id', '=', 'c.risk_entry_id')->join('prevent_risk_matrix_tasks as t', 't.id', '=', 'r.risk_matrix_task_id')->join('prevent_risk_matrix_processes as p', 'p.id', '=', 't.risk_matrix_process_id')->selectRaw('COUNT(*)')->whereColumn('p.risk_matrix_version_id', DB::raw($currentVersionIdSql))->whereDate('c.due_date', '<', now())->whereNotIn('c.status', ['verified', 'cancelled']),
            'program_progress' => DB::table('prevent_preventive_programs')->select('progress_percentage')->whereColumn('risk_matrix_version_id', DB::raw($currentVersionIdSql))->limit(1),
        ]);
        $paginator = $query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')->paginate($filters['per_page'] ?? 20);

        return RiskMatrixResource::collection($paginator);
    }

    public function store(StoreRiskMatrixRequest $request): JsonResponse
    {
        $created = $this->matrices->create($request->validated(), $request->user());

        return response()->json(['message' => 'Matriz IPER creada.', 'data' => ['matrix' => $created['matrix'], 'version' => $created['version']]], 201);
    }

    public function show(RiskMatrix $riskMatrix, Request $request): JsonResponse
    {
        $this->authorize('view', $riskMatrix);
        $version = $request->integer('version_id')
            ? $riskMatrix->versions()->findOrFail($request->integer('version_id'))
            : ($riskMatrix->activeVersion ?: $riskMatrix->versions()->firstOrFail());

        return response()->json(['data' => $version->load($this->structures->detailRelations())]);
    }

    public function destroy(RiskMatrix $riskMatrix, Request $request): JsonResponse
    {
        $version = $riskMatrix->versions()->sole();
        $this->authorize('delete', $version);
        DB::transaction(function () use ($riskMatrix, $version) {
            foreach ($version->evidences as $evidence) {
                Storage::disk('local')->delete($evidence->file_path);
            }
            RiskEvidence::query()->where('risk_matrix_version_id', $version->id)->delete();
            $this->structures->deleteStructure($version);
            $this->audit->record($riskMatrix, 'draft_deleted', $riskMatrix->toArray(), []);
            $version->delete();
            $riskMatrix->delete();
        });

        return response()->json(['message' => 'Borrador eliminado.']);
    }
}
