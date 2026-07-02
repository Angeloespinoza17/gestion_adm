<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\ActivateConvivenciaProtocolRequest;
use App\Http\Requests\Convivencia\SaveConvivenciaProtocolRequest;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Services\Convivencia\ConvivenciaProtocolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaProtocolController extends Controller
{
    public function __construct(
        private readonly ConvivenciaProtocolService $protocolService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaProtocol::class);

        $query = app(\App\Services\Convivencia\ConvivenciaAccessService::class)
            ->applyProtocolVisibility(
                ConvivenciaProtocol::query()->withCount(['steps', 'activations']),
                $request->user(),
            );

        $query
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('criticality_label'), fn ($builder, $value) => $builder->where('criticality_label', $value))
            ->when($request->query('search'), fn ($builder, $value) => $builder->where('name', 'like', "%{$value}%"));

        return response()->json($query->orderBy('name')->paginate((int) $request->query('per_page', 12)));
    }

    public function activations(Request $request): JsonResponse
    {
        abort_unless(app(\App\Services\Convivencia\ConvivenciaAccessService::class)->canManageProtocols($request->user()) || app(\App\Services\Convivencia\ConvivenciaAccessService::class)->canActivateProtocols($request->user()) || app(\App\Services\Convivencia\ConvivenciaAccessService::class)->canViewCases($request->user()), 403);

        $query = ConvivenciaProtocolActivation::query()->with([
            'protocol:id,name',
            'case:id,folio,status',
            'complaint:id,folio,status',
            'activatedBy:id,name',
            'currentStep:id,stage_name',
        ]);

        $query
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('protocol_id'), fn ($builder, $value) => $builder->where('protocol_id', $value))
            ->when($request->query('case_id'), fn ($builder, $value) => $builder->where('case_id', $value))
            ->when($request->query('complaint_id'), fn ($builder, $value) => $builder->where('complaint_id', $value));

        return response()->json($query->latest('activated_at')->paginate((int) $request->query('per_page', 12)));
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

    public function show(ConvivenciaProtocol $protocol): JsonResponse
    {
        $this->authorize('view', $protocol);

        return response()->json([
            'data' => $protocol->load([
                'type:id,name',
                'criticality:id,name,color',
                'steps',
                'activations.protocol:id,name',
            ]),
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
        abort_unless(app(\App\Services\Convivencia\ConvivenciaAccessService::class)->canActivateProtocols($request->user()), 403);

        $activation = $this->protocolService->activate($request->validated(), $request->user());

        return response()->json([
            'message' => 'Protocolo activado correctamente.',
            'data' => $activation,
        ], 201);
    }

    public function showActivation(ConvivenciaProtocolActivation $activation): JsonResponse
    {
        abort_unless(app(\App\Services\Convivencia\ConvivenciaAccessService::class)->canViewProtocolActivation(request()->user(), $activation), 403);

        return response()->json([
            'data' => $activation->load([
                'protocol:id,name',
                'case:id,folio,status',
                'complaint:id,folio,status',
                'currentStep:id,stage_name,step_order',
                'activatedBy:id,name',
                'logs.createdBy:id,name',
                'logs.protocolStep:id,stage_name',
            ]),
        ]);
    }

    public function updateActivation(ActivateConvivenciaProtocolRequest $request, ConvivenciaProtocolActivation $activation): JsonResponse
    {
        abort_unless(app(\App\Services\Convivencia\ConvivenciaAccessService::class)->canActivateProtocols($request->user()), 403);

        $updated = $this->protocolService->updateActivation($activation, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Activación de protocolo actualizada correctamente.',
            'data' => $updated,
        ]);
    }
}
