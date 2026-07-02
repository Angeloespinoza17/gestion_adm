<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Porter\StorePorterKeyLoanRequest;
use App\Http\Requests\Porter\StorePorterKeyRequest;
use App\Http\Requests\Porter\UpdatePorterKeyLoanReturnRequest;
use App\Models\PorterKey;
use App\Models\PorterKeyLoan;
use App\Services\Porter\PorterAccessService;
use App\Services\Porter\PorterAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PorterKeyController extends Controller
{
    public function __construct(
        private readonly PorterAccessService $accessService,
        private readonly PorterAuditService $auditService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $keys = PorterKey::query()
            ->with(['dependency:id,name,code', 'department:id,name'])
            ->withCount([
                'activeLoan as active_loans_count',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        $loans = PorterKeyLoan::query()
            ->with([
                'porterKey:id,code,name',
                'staff:id,full_name,rut',
                'dependency:id,name,code',
                'registeredBy:id,name',
                'returnedToBy:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('requester_name', 'like', "%{$search}%")
                        ->orWhere('requester_rut', 'like', "%{$search}%")
                        ->orWhereHas('porterKey', fn (Builder $keyQuery) => $keyQuery->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->latest('checked_out_at')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'keys' => $keys,
            'loans' => $loans,
            'summary' => [
                'total_keys' => $keys->count(),
                'active_loans' => PorterKeyLoan::query()->where('status', 'prestada')->count(),
                'observed_loans' => PorterKeyLoan::query()->where('status', 'observada')->count(),
            ],
        ]);
    }

    public function store(StorePorterKeyRequest $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('gestionar_llaves_porteria') || $request->user()?->isSuperAdmin(), 403);

        $key = PorterKey::create([
            'maintenance_dependency_id' => $request->integer('maintenance_dependency_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'code' => trim((string) $request->input('code')),
            'name' => trim((string) $request->input('name')),
            'observations' => $request->input('observations'),
            'active' => $request->boolean('active', true),
        ]);

        $this->auditService->log(
            $key,
            'registro_llave',
            null,
            $key->active ? 'activa' : 'inactiva',
            'Llave registrada en portería.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Llave registrada correctamente.',
            'data' => $key->fresh(['dependency:id,name,code', 'department:id,name']),
        ], 201);
    }

    public function loan(StorePorterKeyLoanRequest $request, PorterKey $porterKey): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('gestionar_llaves_porteria') || $request->user()?->isSuperAdmin(), 403);

        abort_if(!$porterKey->active, 422, 'La llave seleccionada está inactiva.');
        abort_if($porterKey->loans()->where('status', 'prestada')->exists(), 422, 'La llave ya se encuentra prestada.');

        $loan = PorterKeyLoan::create([
            'porter_key_id' => $porterKey->id,
            'staff_id' => $request->integer('staff_id') ?: null,
            'maintenance_dependency_id' => $request->integer('maintenance_dependency_id') ?: null,
            'registered_by' => $request->user()?->id,
            'status' => 'prestada',
            'checked_out_at' => now(),
            'expected_return_at' => $request->input('expected_return_at'),
            'requester_name' => trim((string) $request->input('requester_name')),
            'requester_rut' => $request->input('requester_rut'),
            'purpose' => $request->input('purpose'),
            'observations' => $request->input('observations'),
        ]);

        $this->auditService->log(
            $loan,
            'prestamo_llave',
            null,
            $loan->status,
            'Préstamo de llave registrado.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Préstamo de llave registrado correctamente.',
            'data' => $loan->fresh(['porterKey:id,code,name', 'staff:id,full_name,rut', 'dependency:id,name,code', 'registeredBy:id,name']),
        ], 201);
    }

    public function returnLoan(UpdatePorterKeyLoanReturnRequest $request, PorterKeyLoan $porterKeyLoan): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('gestionar_llaves_porteria') || $request->user()?->isSuperAdmin(), 403);
        abort_if($porterKeyLoan->status === 'devuelta', 422, 'La llave ya fue devuelta.');

        $fromStatus = $porterKeyLoan->status;
        $status = $request->input('status', 'devuelta');

        $porterKeyLoan->update([
            'status' => $status,
            'returned_at' => now(),
            'returned_to_by' => $request->user()?->id,
            'return_observations' => $request->input('return_observations'),
        ]);

        $this->auditService->log(
            $porterKeyLoan->fresh(),
            'devolucion_llave',
            $fromStatus,
            $status,
            $request->input('return_observations') ?: 'Devolución de llave registrada.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Devolución de llave registrada correctamente.',
            'data' => $porterKeyLoan->fresh(['porterKey:id,code,name', 'staff:id,full_name,rut', 'dependency:id,name,code', 'registeredBy:id,name', 'returnedToBy:id,name']),
        ]);
    }
}
