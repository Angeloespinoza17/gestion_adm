<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\MaintenanceDependency;
use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityIncidentStatus;
use App\Models\Security\SecurityRound;
use App\Models\Security\SecurityRoundSector;
use App\Models\Security\SecurityShift;
use App\Models\Staff;
use App\Models\User;
use App\Services\Security\SecurityAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityCatalogController extends Controller
{
    public function __construct(
        private readonly SecurityAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'shift_statuses' => SecurityShift::STATUS_OPTIONS,
            'schedule_types' => SecurityShift::SCHEDULE_OPTIONS,
            'weekday_options' => SecurityShift::WEEKDAY_OPTIONS,
            'round_statuses' => SecurityRound::STATUS_OPTIONS,
            'sector_states' => SecurityRoundSector::STATE_OPTIONS,
            'priorities' => SecurityIncident::PRIORITY_OPTIONS,
            'incident_statuses' => SecurityIncidentStatus::query()
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name', 'color', 'is_closed']),
            'staff' => Staff::query()
                ->with('cargo:id,name')
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'rut', 'cargo_id']),
            'dependencies' => MaintenanceDependency::query()
                ->physicalSpaces()
                ->where('active', true)
                ->orderBy('distribution')
                ->orderBy('sector')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'distribution', 'sector', 'zone', 'responsible_staff_id']),
            'inventory_items' => InventoryItem::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'code', 'name', 'dependency_id', 'responsible_user_id']),
            'responsible_users' => User::query()
                ->with('staff:id,full_name,rut')
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id']),
            'current_user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'staff_id' => $request->user()->staff_id,
            ],
            'capabilities' => [
                'can_manage_shifts' => $this->accessService->canManageShifts($request->user()),
                'can_register_rounds' => $this->accessService->canRegisterRounds($request->user()),
                'can_manage_incidents' => $this->accessService->canManageIncidents($request->user()),
                'can_export' => $this->accessService->canExport($request->user()),
            ],
        ]);
    }
}
