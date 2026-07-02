<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Porter\StorePorterExternalServiceEntryRequest;
use App\Http\Requests\Porter\UpdatePorterExternalServiceEntryStatusRequest;
use App\Models\PorterExternalServiceEntry;
use App\Services\Porter\PorterAccessService;
use App\Services\Porter\PorterAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PorterExternalServiceEntryController extends Controller
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

        $query = PorterExternalServiceEntry::query()
            ->with([
                'responsibleStaff:id,full_name,rut',
                'dependency:id,name,code',
                'registeredBy:id,name',
                'closedBy:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('service_type', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('vehicle_plate', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($request->query('date_from'), fn (Builder $query, $value) => $query->whereDate('entered_at', '>=', $value))
            ->when($request->query('date_to'), fn (Builder $query, $value) => $query->whereDate('entered_at', '<=', $value));

        return response()->json(
            $query->latest('entered_at')->latest('id')->paginate((int) $request->query('per_page', 15))
        );
    }

    public function store(StorePorterExternalServiceEntryRequest $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('registrar_proveedores_porteria') || $request->user()?->isSuperAdmin(), 403);

        $entry = PorterExternalServiceEntry::create([
            'responsible_staff_id' => $request->integer('responsible_staff_id') ?: null,
            'maintenance_dependency_id' => $request->integer('maintenance_dependency_id') ?: null,
            'registered_by' => $request->user()?->id,
            'status' => 'en_curso',
            'entered_at' => now(),
            'service_type' => $request->string('service_type')->toString(),
            'company_name' => $request->input('company_name'),
            'contact_name' => $request->string('contact_name')->toString(),
            'contact_rut' => $request->input('contact_rut'),
            'phone' => $request->input('phone'),
            'vehicle_plate' => $request->input('vehicle_plate'),
            'observations' => $request->input('observations'),
        ]);

        $this->auditService->log(
            $entry,
            'registro_servicio_externo',
            null,
            $entry->status,
            'Ingreso de proveedor o servicio externo registrado.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Ingreso de proveedor registrado correctamente.',
            'data' => $entry->fresh(['responsibleStaff:id,full_name,rut', 'dependency:id,name,code', 'registeredBy:id,name']),
        ], 201);
    }

    public function exit(UpdatePorterExternalServiceEntryStatusRequest $request, PorterExternalServiceEntry $porterExternalServiceEntry): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('registrar_proveedores_porteria') || $request->user()?->isSuperAdmin(), 403);

        $fromStatus = $porterExternalServiceEntry->status;

        $porterExternalServiceEntry->update([
            'status' => $request->input('status'),
            'exited_at' => now(),
            'closed_by' => $request->user()?->id,
            'observations' => $request->filled('observations')
                ? trim($porterExternalServiceEntry->observations . "\n" . $request->input('observations'))
                : $porterExternalServiceEntry->observations,
        ]);

        $this->auditService->log(
            $porterExternalServiceEntry->fresh(),
            'cierre_servicio_externo',
            $fromStatus,
            $porterExternalServiceEntry->status,
            $request->input('observations') ?: 'Salida de proveedor registrada.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Salida de proveedor registrada correctamente.',
            'data' => $porterExternalServiceEntry->fresh(['responsibleStaff:id,full_name,rut', 'dependency:id,name,code', 'registeredBy:id,name', 'closedBy:id,name']),
        ]);
    }
}
