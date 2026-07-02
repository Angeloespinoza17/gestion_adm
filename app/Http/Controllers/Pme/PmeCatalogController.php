<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Services\Pme\PmeAccessService;
use App\Services\Pme\PmeCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeCatalogController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
        private readonly PmeCatalogService $catalogService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json($this->catalogService->build($request->user()));
    }
}
