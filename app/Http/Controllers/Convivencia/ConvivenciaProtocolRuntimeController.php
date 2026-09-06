<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\UpdateConvivenciaProtocolActivationPartRequest;
use App\Http\Requests\Convivencia\UpdateConvivenciaProtocolActivationStepRequest;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\Convivencia\ConvivenciaProtocolActivationPart;
use App\Models\Convivencia\ConvivenciaProtocolActivationStep;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaProtocolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaProtocolRuntimeController extends Controller
{
    public function __construct(
        private readonly ConvivenciaProtocolService $protocolService,
        private readonly ConvivenciaAccessService $accessService,
    ) {}

    public function progress(Request $request, ConvivenciaProtocolActivation $activation): JsonResponse
    {
        abort_unless($this->accessService->canViewProtocolActivation($request->user(), $activation), 403);

        return response()->json(['data' => $this->protocolService->progress($activation, $request->user())]);
    }

    public function materialize(Request $request, ConvivenciaProtocolActivation $activation): JsonResponse
    {
        $this->authorizeRuntimeChange($request, $activation);
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'message' => 'Ruta histórica preparada para continuar su gestión.',
            'data' => $this->protocolService->materializeLegacyActivation(
                $activation,
                $request->user(),
                (int) $validated['revision']
            ),
        ]);
    }

    public function updateStep(
        UpdateConvivenciaProtocolActivationStepRequest $request,
        ConvivenciaProtocolActivationStep $step,
    ): JsonResponse {
        $this->authorizeRuntimeChange($request, $step->activation);

        return response()->json([
            'message' => 'Paso del protocolo actualizado correctamente.',
            'data' => $this->protocolService->updateRuntimeStep($step, $request->validated(), $request->user()),
        ]);
    }

    public function completeStep(
        UpdateConvivenciaProtocolActivationStepRequest $request,
        ConvivenciaProtocolActivationStep $step,
    ): JsonResponse {
        $this->authorizeRuntimeChange($request, $step->activation);

        return response()->json([
            'message' => 'Paso completado y avance recalculado correctamente.',
            'data' => $this->protocolService->completeRuntimeStep($step, $request->validated(), $request->user()),
        ]);
    }

    public function updatePart(
        UpdateConvivenciaProtocolActivationPartRequest $request,
        ConvivenciaProtocolActivationPart $part,
    ): JsonResponse {
        $this->authorizeRuntimeChange($request, $part->activation);

        return response()->json([
            'message' => 'Parte del protocolo actualizada correctamente.',
            'data' => $this->protocolService->updateRuntimePart($part, $request->validated(), $request->user()),
        ]);
    }

    private function authorizeRuntimeChange(Request $request, ConvivenciaProtocolActivation $activation): void
    {
        abort_unless(
            $this->accessService->canActivateProtocols($request->user())
            && $this->accessService->canViewProtocolActivation($request->user(), $activation),
            403
        );
    }
}
