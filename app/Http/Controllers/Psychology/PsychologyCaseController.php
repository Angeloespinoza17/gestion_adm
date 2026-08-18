<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Http\Requests\Psychology\AssignPsychologyRequest;
use App\Http\Requests\Psychology\OpenPsychologyCaseRequest;
use App\Http\Resources\Psychology\PsychologyCaseResource;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyReferral;
use App\Models\User;
use App\Services\Psychology\PsychologyAccessService;
use App\Services\Psychology\PsychologyAuditService;
use App\Services\Psychology\PsychologyWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PsychologyCaseController extends Controller
{
    public function __construct(private readonly PsychologyAccessService $access, private readonly PsychologyWorkflowService $workflow, private readonly PsychologyAuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:40'], 'priority' => ['nullable', 'string', 'max:20'], 'responsible_user_id' => ['nullable', 'integer'], 'per_page' => ['nullable', 'integer', 'between:5,100']]);
        $query = PsychologyCase::query()->filter($filters)->with(['student:id,first_name,last_name,registered_name,rut', 'student.enrollments', 'responsibleUser:id,name'])->latest('last_activity_at');
        $this->access->applyCaseVisibility($query, $request->user());

        return response()->json(PsychologyCaseResource::collection($query->paginate($filters['per_page'] ?? 20))->response()->getData(true));
    }

    public function open(OpenPsychologyCaseRequest $request, PsychologyReferral $referral): JsonResponse
    {
        abort_unless($request->user()->hasPermission('psychology.cases.create'), 403);
        $this->authorize('manage', $referral);
        $case = $this->workflow->openCase($referral, $request->validated(), $request->user());

        return response()->json(['message' => 'Caso abierto.', 'data' => new PsychologyCaseResource($case->load(['student.enrollments', 'responsibleUser']))], 201);
    }

    public function show(PsychologyCase $case): PsychologyCaseResource
    {
        $this->authorize('view', $case);
        $this->audit->record('case.viewed', $case, request()->user());

        return new PsychologyCaseResource($case->load(['student.enrollments', 'responsibleUser', 'assignments.user:id,name', 'collaborators:id,name', 'referrals.student', 'plans.versions', 'activities.responsibleUser:id,name', 'activities.addenda.author:id,name', 'riskAssessments.actions.responsibleUser:id,name', 'tasks.responsibleUser:id,name', 'documents.uploadedBy:id,name', 'consents', 'externalReferrals', 'sharedFeedback.author:id,name', 'closures.author:id,name', 'reopenings.author:id,name']));
    }

    public function assign(AssignPsychologyRequest $request, PsychologyCase $case): PsychologyCaseResource
    {
        abort_unless($request->user()->hasPermission('psychology.cases.reassign'), 403);
        $this->authorize('view', $case);
        $professional = User::query()->where('active', true)->findOrFail($request->integer('user_id'));

        return new PsychologyCaseResource($this->workflow->reassignCase($case, $professional, $request->user(), $request->string('reason')->toString())->load(['student.enrollments', 'responsibleUser']));
    }

    public function close(Request $request, PsychologyCase $case): PsychologyCaseResource
    {
        $this->authorize('close', $case);
        $payload = $request->validate(['closure_type' => ['required', 'string', 'max:80'], 'reason' => ['required', 'string', 'max:4000'], 'result_summary' => ['required', 'string', 'max:8000'], 'recommendations' => ['nullable', 'string', 'max:6000']]);

        return new PsychologyCaseResource($this->workflow->closeCase($case, $payload, $request->user())->load(['student.enrollments', 'responsibleUser']));
    }

    public function reopen(Request $request, PsychologyCase $case): PsychologyCaseResource
    {
        $this->authorize('reopen', $case);
        $data = $request->validate(['reason' => ['required', 'string', 'max:4000']]);

        return new PsychologyCaseResource($this->workflow->reopenCase($case, $data['reason'], $request->user())->load(['student.enrollments', 'responsibleUser']));
    }
}
