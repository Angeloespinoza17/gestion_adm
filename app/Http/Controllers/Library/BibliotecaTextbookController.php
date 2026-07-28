<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveBibliotecaTextoOrdenRequest;
use App\Http\Requests\Library\SaveBibliotecaTextoRecepcionRequest;
use App\Http\Requests\Library\UpdateBibliotecaTextoEntregaRequest;
use App\Models\Library\BibliotecaTextoEntrega;
use App\Models\Library\BibliotecaTextoOrden;
use App\Models\Library\BibliotecaTextoRecepcion;
use App\Services\Library\BibliotecaAccessService;
use App\Services\Library\BibliotecaTextbookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibliotecaTextbookController extends Controller
{
    public function __construct(
        private readonly BibliotecaTextbookService $service,
        private readonly BibliotecaAccessService $accessService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'titles' => $this->service->titleCatalog(),
            'stock' => $this->service->stockSummary(),
            'receptions' => BibliotecaTextoRecepcion::query()
                ->with([
                    'items.textoTitulo:id,title,subject,publisher,isbn,education_level_id',
                    'items.educationLevel:id,name',
                    'receivedBy:id,name',
                ])
                ->latest('received_at')
                ->limit(30)
                ->get(),
            'orders' => BibliotecaTextoOrden::query()
                ->with([
                    'academicYear:id,name,year',
                    'educationLevel:id,name,type',
                    'courseSection:id,display_name',
                    'items.textoTitulo:id,title,subject,publisher,isbn,education_level_id',
                ])
                ->withCount([
                    'deliveries',
                    'deliveries as pending_deliveries_count' => fn ($query) => $query->whereIn('status', ['pendiente', 'parcial']),
                ])
                ->latest('id')
                ->limit(30)
                ->get(),
        ]);
    }

    public function storeReception(SaveBibliotecaTextoRecepcionRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canManageTextbooks($request->user()), 403);

        return response()->json([
            'message' => 'Recepción de textos registrada correctamente.',
            'data' => $this->service->createReception($request->validated(), $request->user()),
        ], 201);
    }

    public function storeOrder(SaveBibliotecaTextoOrdenRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canManageTextbooks($request->user()), 403);

        return response()->json([
            'message' => 'Orden de entrega creada correctamente.',
            'data' => $this->service->createOrder($request->validated(), $request->user()),
        ], 201);
    }

    public function showOrder(Request $request, BibliotecaTextoOrden $orden): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => $orden->load([
                'academicYear:id,name,year',
                'educationLevel:id,name,type',
                'courseSection:id,display_name',
                'items.textoTitulo:id,title,subject,publisher,isbn,education_level_id',
                'deliveries.student:id,first_name,last_name,registered_name,rut',
                'deliveries.items.orderItem',
                'deliveries.deliveredBy:id,name',
                'preparedBy:id,name',
            ]),
        ]);
    }

    public function generateRoster(Request $request, BibliotecaTextoOrden $orden): JsonResponse
    {
        abort_unless($this->accessService->canManageTextbooks($request->user()), 403);

        return response()->json([
            'message' => 'Listado de entrega generado desde la matrícula vigente.',
            'data' => $this->service->generateRoster($orden, $request->user()),
        ]);
    }

    public function updateDelivery(
        UpdateBibliotecaTextoEntregaRequest $request,
        BibliotecaTextoEntrega $entrega,
    ): JsonResponse {
        abort_unless($this->accessService->canManageTextbooks($request->user()), 403);

        return response()->json([
            'message' => 'Entrega actualizada correctamente.',
            'data' => $this->service->updateDelivery($entrega, $request->validated(), $request->user()),
        ]);
    }
}
