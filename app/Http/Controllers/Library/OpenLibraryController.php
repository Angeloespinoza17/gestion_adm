<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Services\Library\OpenLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenLibraryController extends Controller
{
    public function __construct(
        private readonly OpenLibraryService $openLibraryService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:191'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json([
            'data' => $this->openLibraryService->search(
                $payload['q'],
                (int) ($payload['limit'] ?? 8),
            ),
            'provider' => 'Open Library',
        ]);
    }
}
