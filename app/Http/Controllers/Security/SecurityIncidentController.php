<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\StoreSecurityIncidentCommentRequest;
use App\Http\Requests\Security\UpdateSecurityIncidentRequest;
use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityIncidentComment;
use App\Services\Security\SecurityAccessService;
use App\Services\Security\SecurityIncidentAlertService;
use App\Services\Security\SecurityRoundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SecurityIncidentController extends Controller
{
    public function __construct(
        private readonly SecurityAccessService $accessService,
        private readonly SecurityRoundService $roundService,
        private readonly SecurityIncidentAlertService $alertService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SecurityIncident::class);

        $search = trim((string) $request->query('search'));
        $priority = trim((string) $request->query('priority'));
        $statusId = $request->query('status_id');
        $responsibleUserId = $request->query('responsible_user_id');
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));
        $sector = trim((string) $request->query('sector'));
        $pendingOnly = filter_var($request->query('pending_only'), FILTER_VALIDATE_BOOLEAN);

        $query = $this->accessService->visibleIncidentsQuery($request->user())
            ->with([
                'status:id,code,name,color,is_closed',
                'currentResponsible:id,name,email',
                'shift:id,staff_id,coverage_label,scheduled_start_at,status',
                'shift.staff:id,full_name',
                'round:id,security_shift_id,round_number,recorded_at,act_number',
                'dependency:id,code,name,sector,zone',
                'inventoryItem:id,code,name',
            ])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('sector_name', 'like', "%{$search}%")
                        ->orWhereHas('shift.staff', fn ($staffQuery) => $staffQuery->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($priority !== '', fn ($builder) => $builder->where('priority', $priority))
            ->when($statusId, fn ($builder) => $builder->where('status_id', $statusId))
            ->when($responsibleUserId, fn ($builder) => $builder->where('current_responsible_user_id', $responsibleUserId))
            ->when($from !== '', fn ($builder) => $builder->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($builder) => $builder->whereDate('created_at', '<=', $to))
            ->when($sector !== '', fn ($builder) => $builder->where('sector_name', 'like', "%{$sector}%"))
            ->when($pendingOnly, fn ($builder) => $builder->whereHas('status', fn ($statusQuery) => $statusQuery->where('is_closed', false)))
            ->orderByRaw("
                CASE priority
                    WHEN 'critica' THEN 1
                    WHEN 'alta' THEN 2
                    WHEN 'media' THEN 3
                    ELSE 4
                END
            ")
            ->orderByDesc('created_at');

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
    }

    public function show(SecurityIncident $securityIncident): JsonResponse
    {
        $this->authorize('view', $securityIncident);

        return response()->json([
            'data' => $securityIncident->load([
                'status:id,code,name,color,is_closed',
                'reportedBy:id,name,email',
                'currentResponsible:id,name,email',
                'shift:id,staff_id,coverage_label,scheduled_start_at,scheduled_end_at,status',
                'shift.staff:id,full_name,rut',
                'round:id,security_shift_id,round_number,recorded_at,act_number,overall_status,observations',
                'roundSector:id,security_round_id,sector_name,sector_state,observations',
                'dependency:id,code,name,distribution,sector,zone,responsible_staff_id',
                'dependency.responsibleStaff:id,full_name',
                'inventoryItem:id,code,name',
                'evidences',
                'assignments.user:id,name,email',
                'assignments.assignedBy:id,name,email',
                'comments.user:id,name,email',
                'comments.status:id,code,name,color,is_closed',
                'comments.assignedTo:id,name,email',
            ]),
        ]);
    }

    public function update(UpdateSecurityIncidentRequest $request, SecurityIncident $securityIncident): JsonResponse
    {
        $this->authorize('update', $securityIncident);

        DB::transaction(function () use ($request, $securityIncident) {
            $payload = $request->validated();

            $securityIncident->update([
                'status_id' => $payload['status_id'] ?? $securityIncident->status_id,
                'priority' => $payload['priority'],
                'response_due_at' => $payload['response_due_at'] ?? $securityIncident->response_due_at,
                'response_summary' => $payload['response_summary'] ?? $securityIncident->response_summary,
                'closure_evidence_notes' => $payload['closure_evidence_notes'] ?? $securityIncident->closure_evidence_notes,
                'responded_at' => !empty($payload['response_summary']) ? now() : $securityIncident->responded_at,
            ]);

            $assigneeIds = collect((array) ($payload['assignee_user_ids'] ?? []))
                ->filter()
                ->map(fn ($value) => (int) $value)
                ->values()
                ->all();

            if (isset($payload['current_responsible_user_id']) && !$assigneeIds) {
                $assigneeIds = [(int) $payload['current_responsible_user_id']];
            }

            if ($assigneeIds) {
                $this->roundService->syncAssignments($securityIncident, $assigneeIds, $request->user());
            }

            if (!empty($payload['comment'])) {
                SecurityIncidentComment::create([
                    'security_incident_id' => $securityIncident->id,
                    'user_id' => $request->user()->id,
                    'status_id' => $payload['status_id'] ?? $securityIncident->status_id,
                    'assigned_to_user_id' => $assigneeIds[0] ?? $securityIncident->current_responsible_user_id,
                    'comment' => $payload['comment'],
                    'responded_at' => now(),
                    'is_internal' => (bool) ($payload['is_internal'] ?? false),
                ]);
            }

            foreach ((array) $request->file('evidence_files', []) as $file) {
                $this->storeIncidentEvidence($securityIncident, $file, $request->user()->id, 'cierre');
            }

            $securityIncident->load('status:id,is_closed,code');
            if ($securityIncident->status?->is_closed) {
                $securityIncident->update([
                    'resolved_at' => $securityIncident->resolved_at ?: now(),
                ]);
            } elseif ($securityIncident->resolved_at) {
                $securityIncident->update([
                    'resolved_at' => null,
                ]);
            }

            $this->alertService->dispatchIfNeeded($securityIncident->fresh([
                'reportedBy:id,name,email',
                'shift.staff:id,full_name',
                'assignments.user:id,name,email,active',
                'currentResponsible:id,name,email,active',
            ]));
        });

        return response()->json([
            'message' => 'Novedad actualizada correctamente.',
            'data' => $securityIncident->fresh()->load([
                'status:id,code,name,color,is_closed',
                'currentResponsible:id,name,email',
                'assignments.user:id,name,email',
                'comments.user:id,name,email',
                'comments.status:id,code,name,color,is_closed',
                'evidences',
            ]),
        ]);
    }

    public function storeComment(StoreSecurityIncidentCommentRequest $request, SecurityIncident $securityIncident): JsonResponse
    {
        $this->authorize('update', $securityIncident);

        DB::transaction(function () use ($request, $securityIncident) {
            $payload = $request->validated();

            if (!empty($payload['assigned_to_user_id'])) {
                $this->roundService->syncAssignments($securityIncident, [(int) $payload['assigned_to_user_id']], $request->user());
            }

            SecurityIncidentComment::create([
                'security_incident_id' => $securityIncident->id,
                'user_id' => $request->user()->id,
                'status_id' => $payload['status_id'] ?? $securityIncident->status_id,
                'assigned_to_user_id' => $payload['assigned_to_user_id'] ?? $securityIncident->current_responsible_user_id,
                'comment' => $payload['comment'],
                'responded_at' => now(),
                'is_internal' => (bool) ($payload['is_internal'] ?? false),
            ]);

            $securityIncident->update([
                'status_id' => $payload['status_id'] ?? $securityIncident->status_id,
                'responded_at' => now(),
            ]);

            $securityIncident->load('status:id,is_closed');
            if ($securityIncident->status?->is_closed) {
                $securityIncident->update(['resolved_at' => $securityIncident->resolved_at ?: now()]);
            }

            foreach ((array) $request->file('evidence_files', []) as $file) {
                $this->storeIncidentEvidence($securityIncident, $file, $request->user()->id, 'seguimiento');
            }
        });

        return response()->json([
            'message' => 'Seguimiento registrado correctamente.',
            'data' => $securityIncident->fresh()->load([
                'status:id,code,name,color,is_closed',
                'currentResponsible:id,name,email',
                'comments.user:id,name,email',
                'comments.status:id,code,name,color,is_closed',
                'comments.assignedTo:id,name,email',
                'evidences',
            ]),
        ]);
    }

    private function storeIncidentEvidence(SecurityIncident $incident, UploadedFile $file, int $userId, string $kind): void
    {
        $path = $file->store(sprintf('security/SecurityIncident/%d', $incident->id), 'public');

        $incident->evidences()->create([
            'uploaded_by_user_id' => $userId,
            'kind' => $kind,
            'file_path' => $path,
            'caption' => $incident->title,
            'taken_at' => now(),
        ]);
    }
}
