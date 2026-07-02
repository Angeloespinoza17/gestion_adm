<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityNotification;
use App\Models\Security\SecurityRound;
use App\Models\Security\SecurityShift;
use App\Services\Security\SecurityAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityDashboardController extends Controller
{
    public function __construct(
        private readonly SecurityAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $visibleShiftIds = $this->accessService->visibleShiftsQuery($request->user())->select('security_shifts.id');
        $visibleIncidentQuery = $this->accessService->visibleIncidentsQuery($request->user());

        $roundsQuery = SecurityRound::query()->whereIn('security_shift_id', $visibleShiftIds);
        $incidentsBase = (clone $visibleIncidentQuery);

        $respondedIncidents = (clone $incidentsBase)
            ->whereNotNull('responded_at')
            ->get(['created_at', 'responded_at']);

        $averageResponseMinutes = $respondedIncidents->isEmpty()
            ? null
            : round(
                $respondedIncidents->avg(fn ($incident) => $incident->created_at?->diffInMinutes($incident->responded_at)),
                1
            );

        $pendingStatusIds = DB::table('security_incident_statuses')
            ->whereIn('code', ['pendiente', 'en_revision', 'derivada'])
            ->pluck('id');

        return response()->json([
            'totals' => [
                'rounds_total' => (clone $roundsQuery)->count(),
                'incidents_total' => (clone $incidentsBase)->count(),
                'pending_incidents' => (clone $incidentsBase)->whereIn('status_id', $pendingStatusIds)->count(),
                'critical_incidents' => (clone $incidentsBase)->where('priority', 'critica')->count(),
                'resolved_incidents' => (clone $incidentsBase)->whereHas('status', fn ($query) => $query->where('is_closed', true))->count(),
                'unresolved_incidents' => (clone $incidentsBase)->whereHas('status', fn ($query) => $query->where('is_closed', false))->count(),
                'average_response_minutes' => $averageResponseMinutes,
            ],
            'rounds_by_date' => (clone $roundsQuery)
                ->selectRaw('DATE(recorded_at) as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('label')
                ->limit(14)
                ->get()
                ->reverse()
                ->values(),
            'rounds_by_staff' => SecurityShift::query()
                ->selectRaw('staff.full_name as label, COUNT(security_rounds.id) as total')
                ->join('staff', 'staff.id', '=', 'security_shifts.staff_id')
                ->join('security_rounds', 'security_rounds.security_shift_id', '=', 'security_shifts.id')
                ->whereIn('security_shifts.id', $visibleShiftIds)
                ->groupBy('staff.full_name')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
            'sectors_with_most_incidents' => SecurityIncident::query()
                ->whereIn('security_incidents.id', $visibleIncidentQuery->select('security_incidents.id'))
                ->selectRaw("COALESCE(security_incidents.sector_name, 'Todo el colegio') as label, COUNT(security_incidents.id) as total")
                ->groupBy('label')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
            'recent_notifications' => SecurityNotification::query()
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(8)
                ->get(['id', 'title', 'message', 'priority', 'read_at', 'created_at', 'action_url', 'security_incident_id']),
            'upcoming_shifts' => $this->accessService->visibleShiftsQuery($request->user())
                ->with(['staff:id,full_name'])
                ->whereIn('status', [SecurityShift::STATUS_PROGRAMADO, SecurityShift::STATUS_EN_CURSO])
                ->orderBy('scheduled_start_at')
                ->limit(6)
                ->get(['id', 'staff_id', 'schedule_type', 'scheduled_start_at', 'scheduled_end_at', 'status', 'coverage_label', 'weekdays', 'template_start_time', 'template_end_time', 'recurrence_starts_on', 'recurrence_ends_on', 'parent_shift_id', 'generated_for_date']),
        ]);
    }
}
