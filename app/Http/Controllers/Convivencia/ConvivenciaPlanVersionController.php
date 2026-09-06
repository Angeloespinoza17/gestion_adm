<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaPlanVersion;
use App\Services\Convivencia\ConvivenciaAnnualPlanWorkspaceService;
use App\Services\Convivencia\ConvivenciaPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaPlanVersionController extends Controller
{
    public function restore(
        Request $request,
        ConvivenciaPlan $plan,
        ConvivenciaPlanVersion $version,
        ConvivenciaPlanService $service,
        ConvivenciaAnnualPlanWorkspaceService $workspace,
    ): JsonResponse {
        $this->authorize('update', $plan);
        $payload = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $restored = $service->restoreVersion($plan, $version, $request->user(), (int) $payload['revision']);

        return response()->json([
            'message' => "Se restauró la versión {$version->version_number} como una nueva versión vigente.",
            'data' => $workspace->serializePlan($restored),
        ]);
    }
}
