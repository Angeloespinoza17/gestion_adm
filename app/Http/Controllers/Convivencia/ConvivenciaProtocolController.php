<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\ActivateConvivenciaProtocolRequest;
use App\Http\Requests\Convivencia\SaveConvivenciaProtocolRequest;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaProtocolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaProtocolController extends Controller
{
    public function __construct(
        private readonly ConvivenciaProtocolService $protocolService,
        private readonly ConvivenciaAccessService $accessService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaProtocol::class);

        $search = mb_substr(trim((string) $request->query('search', '')), 0, 120);

        $query = $this->accessService->applyProtocolVisibility(
            ConvivenciaProtocol::query()
                ->withCount('steps')
                ->withCount(['activations' => fn ($activationQuery) => $this->accessService
                    ->applyProtocolActivationVisibility($activationQuery, $request->user())]),
            $request->user(),
        );

        $query
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('criticality_label'), fn ($builder, $value) => $builder->where('criticality_label', $value))
            ->when($search !== '', function ($builder) use ($search) {
                $like = "%{$search}%";

                $builder->where(fn ($searchQuery) => $searchQuery
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('regulatory_source', 'like', $like));
            });

        return response()->json($query->orderBy('name')->paginate($this->perPage($request)));
    }

    public function activations(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManageProtocols($request->user()) || $this->accessService->canActivateProtocols($request->user()) || $this->accessService->canViewCases($request->user()), 403);

        $search = mb_substr(trim((string) $request->query('search', '')), 0, 120);

        $query = $this->accessService->applyProtocolActivationVisibility(ConvivenciaProtocolActivation::query(), $request->user())->with([
            'protocol:id,code,name,revision,status,deleted_at',
            'protocol.steps:id,protocol_id,step_order,stage_name,active',
            'case:id,folio,status',
            'complaint:id,folio,status',
            'activatedBy:id,name',
            'currentStep:id,stage_name',
            'currentActivationStep:id,activation_id,step_order,stage_name,status,due_at,completed_at',
            'runtimeSteps:id,activation_id,step_order,stage_name,status,due_at,completed_at',
        ]);

        $query
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('protocol_id'), fn ($builder, $value) => $builder->where('protocol_id', $value))
            ->when($request->query('case_id'), fn ($builder, $value) => $builder->where('case_id', $value))
            ->when($request->query('complaint_id'), fn ($builder, $value) => $builder->where('complaint_id', $value))
            ->when($search !== '', function ($builder) use ($search) {
                $like = "%{$search}%";

                $builder->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('current_stage_name', 'like', $like)
                        ->orWhereHas('protocol', fn ($protocolQuery) => $protocolQuery
                            ->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like))
                        ->orWhereHas('case', fn ($caseQuery) => $caseQuery->where('folio', 'like', $like))
                        ->orWhereHas('complaint', fn ($complaintQuery) => $complaintQuery->where('folio', 'like', $like));
                });
            });

        $paginator = $query->latest('activated_at')->paginate($this->perPage($request));
        $paginator->getCollection()->each(function (ConvivenciaProtocolActivation $activation) {
            $activation->setAttribute('progress', $this->protocolService->progress($activation, null, false));
        });

        return response()->json($paginator);
    }

    public function store(SaveConvivenciaProtocolRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaProtocol::class);

        $protocol = $this->protocolService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Protocolo registrado correctamente.',
            'data' => $protocol,
        ], 201);
    }

    public function show(Request $request, ConvivenciaProtocol $protocol): JsonResponse
    {
        $this->authorize('view', $protocol);

        $canManage = $this->accessService->canManageProtocols($request->user());
        $linkLoader = function ($query) use ($canManage) {
            $query
                ->when(! $canManage, fn ($links) => $links->whereHas('part', fn ($part) => $part->where('is_sensitive', false)))
                ->with('part');
        };
        $relations = [
            'type:id,name',
            'criticality:id,name,color',
            'steps.partLinks' => $linkLoader,
            'partLinks' => $linkLoader,
        ];
        if ($canManage) {
            $relations['statusLogs'] = fn ($logs) => $logs->with('changedBy:id,name')->limit(25);
        }

        $protocol->load($relations)->loadCount([
            'steps',
            'partLinks',
            'activations' => fn ($activationQuery) => $this->accessService
                ->applyProtocolActivationVisibility($activationQuery, $request->user()),
        ]);

        return response()->json([
            'data' => $protocol,
        ]);
    }

    public function update(SaveConvivenciaProtocolRequest $request, ConvivenciaProtocol $protocol): JsonResponse
    {
        $this->authorize('update', $protocol);

        $updated = $this->protocolService->update($protocol, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Protocolo actualizado correctamente.',
            'data' => $updated,
        ]);
    }

    public function activate(ActivateConvivenciaProtocolRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canActivateProtocols($request->user()), 403);

        $activation = $this->protocolService->activate($request->validated(), $request->user());

        return response()->json([
            'message' => 'Protocolo activado correctamente.',
            'data' => $activation,
        ], 201);
    }

    public function showActivation(ConvivenciaProtocolActivation $activation): JsonResponse
    {
        abort_unless($this->accessService->canViewProtocolActivation(request()->user(), $activation), 403);

        return response()->json([
            'data' => $this->protocolService->presentActivation($activation, request()->user()),
        ]);
    }

    public function updateActivation(ActivateConvivenciaProtocolRequest $request, ConvivenciaProtocolActivation $activation): JsonResponse
    {
        abort_unless(
            $this->accessService->canActivateProtocols($request->user())
            && $this->accessService->canViewProtocolActivation($request->user(), $activation),
            403
        );

        $updated = $this->protocolService->updateActivation($activation, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Activación de protocolo actualizada correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, ConvivenciaProtocol $protocol): JsonResponse
    {
        $this->authorize('delete', $protocol);
        $this->protocolService->archive($protocol, $request->user());

        return response()->json(['message' => 'Protocolo archivado correctamente.']);
    }

    private function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->query('per_page', 12)));
    }
}
