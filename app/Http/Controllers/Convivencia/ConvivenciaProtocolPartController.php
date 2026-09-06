<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaProtocolPartRequest;
use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaProtocolPartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaProtocolPartController extends Controller
{
    public function __construct(private readonly ConvivenciaProtocolPartService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaProtocolPart::class);

        $search = mb_substr(trim((string) $request->query('search', '')), 0, 120);

        $query = ConvivenciaProtocolPart::query()
            ->withCount('links')
            ->when(! $request->user()->isSuperAdmin() && ! app(ConvivenciaAccessService::class)->canManageProtocols($request->user()), fn ($builder) => $builder->where('is_sensitive', false))
            ->when($request->query('category'), fn ($builder, $value) => $builder->where('category', $value))
            ->when($request->has('active'), fn ($builder) => $builder->where('active', $request->boolean('active')))
            ->when($search !== '', function ($builder) use ($search) {
                $like = "%{$search}%";

                $builder->where(fn ($inner) => $inner
                    ->where('title', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('legal_reference', 'like', $like));
            });

        return response()->json($query->orderBy('category')->orderBy('title')->paginate($this->perPage($request)));
    }

    public function store(SaveConvivenciaProtocolPartRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaProtocolPart::class);

        return response()->json([
            'message' => 'Parte de protocolo creada correctamente.',
            'data' => $this->service->store($request->validated(), $request->user()),
        ], 201);
    }

    public function show(ConvivenciaProtocolPart $protocolPart): JsonResponse
    {
        $this->authorize('view', $protocolPart);

        if (! app(ConvivenciaAccessService::class)->canManageProtocols(request()->user())) {
            return response()->json(['data' => $protocolPart]);
        }

        return response()->json(['data' => $protocolPart->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'links.protocol:id,name,revision',
            'links.step:id,protocol_id,stage_name,step_order',
        ])->loadCount('links')]);
    }

    public function update(SaveConvivenciaProtocolPartRequest $request, ConvivenciaProtocolPart $protocolPart): JsonResponse
    {
        $this->authorize('update', $protocolPart);

        return response()->json([
            'message' => 'Parte de protocolo actualizada correctamente.',
            'data' => $this->service->update($protocolPart, $request->validated(), $request->user()),
        ]);
    }

    public function destroy(Request $request, ConvivenciaProtocolPart $protocolPart): JsonResponse
    {
        $this->authorize('delete', $protocolPart);
        $this->service->archive($protocolPart, $request->user());

        return response()->json(['message' => 'Parte de protocolo archivada y desvinculada correctamente.']);
    }

    private function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->query('per_page', 20)));
    }
}
