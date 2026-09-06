<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\ListConvivenciaReferencesRequest;
use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaReferenceService;
use Illuminate\Http\JsonResponse;

class ConvivenciaReferenceController extends Controller
{
    private const MODELS = [
        'cases' => ConvivenciaCase::class,
        'complaints' => ConvivenciaComplaint::class,
        'parts' => ConvivenciaProtocolPart::class,
        'plans' => ConvivenciaPlan::class,
        'protocols' => ConvivenciaProtocol::class,
    ];

    public function __construct(
        private readonly ConvivenciaReferenceService $referenceService,
        private readonly ConvivenciaAccessService $accessService,
    ) {}

    public function __invoke(ListConvivenciaReferencesRequest $request, string $type): JsonResponse
    {
        $validated = $request->validated();
        $this->authorize('viewAny', self::MODELS[$validated['type']]);

        if (
            in_array($type, ['parts', 'protocols'], true)
            && ($validated['include_inactive'] ?? false)
            && ! $this->accessService->canManageProtocols($request->user())
        ) {
            abort(403);
        }

        $paginator = $this->referenceService->paginate($type, $validated, $request->user());
        $paginator->appends($request->safe()->except(['type']));

        return response()->json($paginator);
    }
}
