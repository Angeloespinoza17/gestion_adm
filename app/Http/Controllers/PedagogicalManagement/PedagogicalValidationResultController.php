<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\ResolveValidationResultRequest;
use App\Http\Resources\PedagogicalManagement\ValidationResultResource;
use App\Models\PedagogicalManagement\PedagogicalInstrumentValidationResult;
use App\Services\PedagogicalManagement\PedagogicalInstrumentResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PedagogicalValidationResultController extends Controller
{
    public function resolve(
        ResolveValidationResultRequest $request,
        PedagogicalInstrumentValidationResult $result,
        PedagogicalInstrumentResolutionService $service,
    ): ValidationResultResource {
        $instrument = $result->analysisRun->instrument;
        $this->authorize('resolveValidations', $instrument);
        if ($result->resolved_at) {
            throw ValidationException::withMessages(['result' => 'La observación ya fue resuelta; utiliza reabrir antes de reemplazar su resolución.']);
        }
        $validated = $request->validated();

        return new ValidationResultResource($service->resolve(
            $result, $validated['resolution_status'], $validated['notes'], $request->user(), $request,
        ));
    }

    public function reopen(
        Request $request,
        PedagogicalInstrumentValidationResult $result,
        PedagogicalInstrumentResolutionService $service,
    ): JsonResponse {
        $instrument = $result->analysisRun->instrument;
        $this->authorize('resolveValidations', $instrument);
        $validated = $request->validate(['notes' => ['required', 'string', 'min:5', 'max:3000']]);
        if (! $result->resolved_at) {
            throw ValidationException::withMessages(['result' => 'La observación ya está abierta.']);
        }

        return response()->json(['data' => new ValidationResultResource(
            $service->reopen($result, $validated['notes'], $request->user(), $request),
        )]);
    }
}
