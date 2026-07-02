<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Porter\StorePorterVisitRequest;
use App\Http\Requests\Porter\UpdatePorterVisitStatusRequest;
use App\Models\PorterVisit;
use App\Services\Porter\PorterAccessService;
use App\Services\Porter\PorterAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PorterVisitController extends Controller
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

        $query = PorterVisit::query()
            ->with([
                'visitedStaff:id,full_name,rut',
                'visitedDepartment:id,name',
                'registeredBy:id,name',
                'closedBy:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('visitor_name', 'like', "%{$search}%")
                        ->orWhere('visitor_rut', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhere('visited_person_label', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($request->query('date_from'), fn (Builder $query, $value) => $query->whereDate('entered_at', '>=', $value))
            ->when($request->query('date_to'), fn (Builder $query, $value) => $query->whereDate('entered_at', '<=', $value));

        return response()->json(
            $query->latest('entered_at')->latest('id')->paginate((int) $request->query('per_page', 15))
        );
    }

    public function store(StorePorterVisitRequest $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('registrar_visitas_porteria') || $request->user()?->isSuperAdmin(), 403);

        $visit = PorterVisit::create([
            'visited_staff_id' => $request->integer('visited_staff_id') ?: null,
            'visited_department_id' => $request->integer('visited_department_id') ?: null,
            'registered_by' => $request->user()?->id,
            'status' => 'en_curso',
            'entered_at' => now(),
            'visitor_name' => $request->string('visitor_name')->toString(),
            'visitor_rut' => $request->input('visitor_rut'),
            'purpose' => $request->string('purpose')->toString(),
            'visited_person_label' => $request->input('visited_person_label'),
            'contact_phone' => $request->input('contact_phone'),
            'observations' => $request->input('observations'),
        ]);

        $this->auditService->log(
            $visit,
            'registro_visita',
            null,
            $visit->status,
            'Visita registrada en portería.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Visita registrada correctamente.',
            'data' => $visit->fresh(['visitedStaff:id,full_name,rut', 'visitedDepartment:id,name', 'registeredBy:id,name']),
        ], 201);
    }

    public function exit(UpdatePorterVisitStatusRequest $request, PorterVisit $porterVisit): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('registrar_visitas_porteria') || $request->user()?->isSuperAdmin(), 403);

        $fromStatus = $porterVisit->status;

        $porterVisit->update([
            'status' => $request->input('status'),
            'exited_at' => now(),
            'closed_by' => $request->user()?->id,
            'observations' => $request->filled('observations')
                ? trim($porterVisit->observations . "\n" . $request->input('observations'))
                : $porterVisit->observations,
        ]);

        $this->auditService->log(
            $porterVisit->fresh(),
            'cierre_visita',
            $fromStatus,
            $porterVisit->status,
            $request->input('observations') ?: 'Salida de visita registrada.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Salida de visita registrada correctamente.',
            'data' => $porterVisit->fresh(['visitedStaff:id,full_name,rut', 'visitedDepartment:id,name', 'registeredBy:id,name', 'closedBy:id,name']),
        ]);
    }
}
