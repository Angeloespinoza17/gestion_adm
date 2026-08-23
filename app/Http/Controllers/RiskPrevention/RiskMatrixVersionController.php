<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\SaveRiskMatrixStructureRequest;
use App\Http\Requests\RiskPrevention\UpdateRiskMatrixVersionRequest;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Services\RiskPrevention\RiskMatrixComparisonService;
use App\Services\RiskPrevention\RiskMatrixService;
use App\Services\RiskPrevention\RiskMatrixStructureService;
use App\Services\RiskPrevention\RiskMatrixVersionService;
use App\Services\RiskPrevention\RiskMatrixWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskMatrixVersionController extends Controller
{
    public function __construct(
        private readonly RiskMatrixService $matrices,
        private readonly RiskMatrixStructureService $structures,
        private readonly RiskMatrixVersionService $versions,
        private readonly RiskMatrixComparisonService $comparison,
        private readonly RiskMatrixWorkflow $workflow,
    ) {}

    public function index(RiskMatrix $riskMatrix): JsonResponse
    {
        $this->authorize('view', $riskMatrix);

        return response()->json(['data' => $riskMatrix->versions()->with(['preparedBy:id,name', 'reviewedBy:id,name', 'approvedBy:id,name', 'methodology:id,code,version_number,name'])->get()]);
    }

    public function store(RiskMatrix $riskMatrix, Request $request): JsonResponse
    {
        $this->authorize('createVersion', $riskMatrix);
        $data = $request->validate(['source_version_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:5000']]);
        $source = $riskMatrix->versions()->findOrFail($data['source_version_id']);
        $version = $this->versions->createFrom($riskMatrix, $source, $request->user(), $data['reason']);

        return response()->json(['message' => 'Nueva versión creada sin alterar el historial.', 'data' => $version], 201);
    }

    public function show(RiskMatrixVersion $riskMatrixVersion): JsonResponse
    {
        $this->authorize('view', $riskMatrixVersion);

        return response()->json(['data' => $riskMatrixVersion->load($this->structures->detailRelations())]);
    }

    public function update(UpdateRiskMatrixVersionRequest $request, RiskMatrixVersion $riskMatrixVersion): JsonResponse
    {
        $this->authorize('update', $riskMatrixVersion);
        $version = $this->matrices->update($riskMatrixVersion, $request->validated(), $request->integer('lock_version'));

        return response()->json(['message' => 'Datos generales guardados.', 'data' => $version]);
    }

    public function structure(SaveRiskMatrixStructureRequest $request, RiskMatrixVersion $riskMatrixVersion): JsonResponse
    {
        $this->authorize('update', $riskMatrixVersion);
        $version = $this->structures->replace($riskMatrixVersion->load('methodology'), $request->validated('processes'), $request->integer('lock_version'), $request->user()->id);

        return response()->json(['message' => 'Matriz de riesgos guardada.', 'data' => $version]);
    }

    public function validation(RiskMatrixVersion $riskMatrixVersion): JsonResponse
    {
        $this->authorize('view', $riskMatrixVersion);
        $issues = $this->workflow->validationIssues($riskMatrixVersion);

        return response()->json(['data' => $issues, 'summary' => collect($issues)->countBy('severity')]);
    }

    public function compare(RiskMatrixVersion $riskMatrixVersion, RiskMatrixVersion $other): JsonResponse
    {
        $this->authorize('view', $riskMatrixVersion);
        $this->authorize('view', $other);
        abort_unless($riskMatrixVersion->risk_matrix_id === $other->risk_matrix_id, 404);

        return response()->json(['data' => $this->comparison->compare($riskMatrixVersion, $other)]);
    }
}
