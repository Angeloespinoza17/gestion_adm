<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supply\StoreSupplyRequestRequest;
use App\Models\Supply\SupplyRequest;
use App\Models\Supply\SupplyRequestItem;
use App\Services\Supply\SupplyRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplyRequestController extends Controller
{
    public function index(Request $request, SupplyRequestService $service): JsonResponse
    {
        $filters = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(SupplyRequest::statuses())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $query = SupplyRequest::query()
            ->with(['creator:id,name', 'reviewer:id,name', 'items.supplyItem:id,inventory_item_id,reference_photo_path,updated_at'])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(trim((string) ($filters['search'] ?? '')) !== '', function ($query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function ($query) use ($search): void {
                    $query->where('folio', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%")
                        ->orWhereHas('items', fn ($items) => $items->where('item_name_snapshot', 'like', "%{$search}%"));
                });
            });

        $summary = SupplyRequest::query()
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted")
            ->selectRaw("SUM(CASE WHEN status IN ('under_review', 'ready_to_quote') THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN status = 'quoted' THEN 1 ELSE 0 END) as quoted")
            ->first();

        $requests = $query->latest('created_at')->latest('id')->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => $requests->items(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
            ],
            'summary' => $summary,
            'statuses' => SupplyRequest::statusOptions(),
        ]);
    }

    public function store(StoreSupplyRequestRequest $request, SupplyRequestService $service): JsonResponse
    {
        $supplyRequest = $service->create($request->validated(), $request->user());

        return response()->json([
            'message' => "Solicitud {$supplyRequest->folio} enviada a Superadmin.",
            'data' => $supplyRequest,
        ], 201);
    }

    public function show(SupplyRequest $supplyRequest, SupplyRequestService $service): JsonResponse
    {
        return response()->json(['data' => $service->load($supplyRequest)]);
    }

    public function photo(SupplyRequestItem $item, SupplyRequestService $service): StreamedResponse
    {
        $path = $service->photoPath($item);
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
