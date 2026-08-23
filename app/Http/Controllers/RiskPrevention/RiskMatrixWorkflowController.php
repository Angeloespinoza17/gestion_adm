<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\RiskWorkflowRequest;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Services\RiskPrevention\RiskMatrixWorkflow;
use Illuminate\Http\JsonResponse;

class RiskMatrixWorkflowController extends Controller
{
    public function __construct(private readonly RiskMatrixWorkflow $workflow) {}

    public function submit(RiskWorkflowRequest $request, RiskMatrixVersion $version): JsonResponse
    {
        $this->authorize('submit', $version);

        return response()->json(['message' => 'Matriz enviada a revisión.', 'data' => $this->workflow->submit($version, $request->user(), $request->input('reason'))]);
    }

    public function review(RiskWorkflowRequest $request, RiskMatrixVersion $version): JsonResponse
    {
        $this->authorize('review', $version);
        $request->validate(['notes' => ['required', 'string', 'max:5000']]);

        return response()->json(['message' => 'Revisión técnica registrada.', 'data' => $this->workflow->technicalReview($version, $request->user(), $request->input('notes'))]);
    }

    public function observe(RiskWorkflowRequest $request, RiskMatrixVersion $version): JsonResponse
    {
        $this->authorize('review', $version);
        $request->validate(['reason' => ['required', 'string', 'max:5000']]);

        return response()->json(['message' => 'Observaciones registradas.', 'data' => $this->workflow->observe($version, $request->user(), $request->input('reason'))]);
    }

    public function draft(RiskWorkflowRequest $request, RiskMatrixVersion $version): JsonResponse
    {
        $this->authorize('review', $version);
        $request->validate(['reason' => ['required', 'string', 'max:5000']]);

        return response()->json(['message' => 'Versión devuelta a borrador.', 'data' => $this->workflow->returnToDraft($version, $request->user(), $request->input('reason'))]);
    }

    public function approve(RiskWorkflowRequest $request, RiskMatrixVersion $version): JsonResponse
    {
        $this->authorize('approve', $version);

        return response()->json(['message' => 'Matriz aprobada y programa preventivo sincronizado.', 'data' => $this->workflow->approve($version, $request->user(), $request->only(['justification', 'additional_approval']))]);
    }

    public function archive(RiskWorkflowRequest $request, RiskMatrixVersion $version): JsonResponse
    {
        $this->authorize('archive', $version);
        $request->validate(['reason' => ['required', 'string', 'max:5000']]);

        return response()->json(['message' => 'Versión archivada.', 'data' => $this->workflow->archive($version, $request->user(), $request->input('reason'))]);
    }
}
