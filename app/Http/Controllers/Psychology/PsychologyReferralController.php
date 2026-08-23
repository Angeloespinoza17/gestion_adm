<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Http\Requests\Psychology\AssignPsychologyRequest;
use App\Http\Requests\Psychology\SavePsychologyReferralRequest;
use App\Http\Requests\Psychology\TransitionPsychologyReferralRequest;
use App\Http\Resources\Psychology\PsychologyReferralResource;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyReferral;
use App\Models\User;
use App\Services\Psychology\PsychologyAccessService;
use App\Services\Psychology\PsychologyAuditService;
use App\Services\Psychology\PsychologyWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PsychologyReferralController extends Controller
{
    public function __construct(private readonly PsychologyAccessService $access, private readonly PsychologyWorkflowService $workflow, private readonly PsychologyAuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:40'], 'priority' => ['nullable', 'string', 'max:20'], 'assigned_user_id' => ['nullable', 'integer'], 'student_profile_id' => ['nullable', 'integer'], 'per_page' => ['nullable', 'integer', 'between:5,100']]);
        $query = PsychologyReferral::query()->filter($filters)->with(['student:id,first_name,last_name,registered_name,rut', 'course:id,display_name', 'referredBy:id,name', 'assignedUser:id,name'])->latest();
        $this->access->applyReferralVisibility($query, $request->user());

        return response()->json(PsychologyReferralResource::collection($query->paginate($filters['per_page'] ?? 20))->response()->getData(true));
    }

    public function store(SavePsychologyReferralRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('psychology.referrals.create'), 403);
        $referral = $this->workflow->createReferral($request->validated(), $request->user());

        return response()->json(['message' => $referral->status === 'submitted' ? 'Derivación enviada.' : 'Borrador guardado.', 'data' => new PsychologyReferralResource($referral->load(['student', 'course', 'referredBy', 'assignedUser'])), 'warnings' => $this->duplicateWarnings($referral)], 201);
    }

    public function show(PsychologyReferral $referral): PsychologyReferralResource
    {
        $this->authorize('view', $referral);
        $this->audit->record('referral.viewed', $referral, request()->user());

        return new PsychologyReferralResource($referral->load(['student', 'course', 'referredBy', 'assignedUser', 'histories.changedBy:id,name', 'documents.uploadedBy:id,name']));
    }

    public function update(SavePsychologyReferralRequest $request, PsychologyReferral $referral): PsychologyReferralResource
    {
        $this->authorize('update', $referral);

        return new PsychologyReferralResource($this->workflow->updateDraft($referral, $request->validated(), $request->user())->load(['student', 'course', 'referredBy', 'assignedUser']));
    }

    public function transition(TransitionPsychologyReferralRequest $request, PsychologyReferral $referral): PsychologyReferralResource
    {
        $status = $request->validated('status');
        if ($status === 'submitted') {
            abort_unless((int) $referral->referred_by_user_id === (int) $request->user()->id || $request->user()->hasPermission('psychology.referrals.update'), 403);
        } else {
            $this->authorize('manage', $referral);
        }

        return new PsychologyReferralResource($this->workflow->transitionReferral($referral, $status, $request->user(), $request->validated())->load(['student', 'course', 'referredBy', 'assignedUser', 'histories.changedBy:id,name']));
    }

    public function assign(AssignPsychologyRequest $request, PsychologyReferral $referral): PsychologyReferralResource
    {
        $this->authorize('assign', $referral);
        $professional = User::query()->where('active', true)->findOrFail($request->integer('user_id'));
        if (! $this->access->canBeAssignedToPsychology($professional)) {
            throw ValidationException::withMessages(['user_id' => 'La persona seleccionada no tiene un rol profesional habilitado para Psicología.']);
        }

        return new PsychologyReferralResource($this->workflow->assignReferral($referral, $professional, $request->user(), $request->validated())->load(['student', 'course', 'referredBy', 'assignedUser']));
    }

    private function duplicateWarnings(PsychologyReferral $referral): array
    {
        $days = (int) config('psychology.reiteration_days', 90);
        $warnings = [];
        if (PsychologyCase::query()->where('student_profile_id', $referral->student_profile_id)->where('status', '!=', 'closed')->exists()) {
            $warnings[] = 'La estudiante tiene un caso de Psicología activo.';
        }
        $recent = PsychologyReferral::query()->where('student_profile_id', $referral->student_profile_id)->whereKeyNot($referral->id)->where('created_at', '>=', now()->subDays($days))->whereNotIn('status', ['cancelled', 'rejected'])->count();
        if ($recent > 0) {
            $warnings[] = "Existen {$recent} derivaciones recientes; revisa si corresponde asociar este antecedente.";
        }

        return $warnings;
    }
}
