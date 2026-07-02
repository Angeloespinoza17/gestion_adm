<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoEntrevista;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Models\StudentProfile;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use App\Services\ApoyoProfesional\ApoyoProfesionalStudentContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApoyoProfesionalStudentHistoryController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
        private readonly ApoyoProfesionalStudentContextService $studentContextService,
    ) {
    }

    public function __invoke(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $academicYearId = $request->query('academic_year_id');
        $professionalId = $request->query('attended_by_user_id');
        $attentionType = trim((string) $request->query('attention_type_label'));
        $status = trim((string) $request->query('status'));
        $confidentiality = trim((string) $request->query('confidentiality_level'));
        $area = trim((string) $request->query('professional_area_name'));

        $attentionQuery = $this->accessService->applyAttentionVisibility(
            ApoyoAtencion::query()
                ->with([
                    'professional.staff:id,full_name',
                    'attendedBy:id,name',
                    'attentionType:id,name',
                    'motive:id,name',
                    'derivations.destinationProfessional.staff:id,full_name',
                    'derivations.destinationUser:id,name',
                    'followUps.responsibleProfessional.staff:id,full_name',
                    'followUps.responsibleUser:id,name',
                    'documents.uploadedBy:id,name',
                ])
                ->where('student_profile_id', $studentProfile->id),
            $request->user(),
        );

        $attentionQuery
            ->when($academicYearId, fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->when($professionalId, fn (Builder $query) => $query->where('attended_by_user_id', $professionalId))
            ->when($attentionType !== '', fn (Builder $query) => $query->where('attention_type_label', $attentionType))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($confidentiality !== '', fn (Builder $query) => $query->where('confidentiality_level', $confidentiality))
            ->when($area !== '', fn (Builder $query) => $query->where('professional_area_name', $area));

        $attentions = $attentionQuery->latest('attended_at')->get();
        $attentionIds = $attentions->pluck('id');

        $derivations = $this->accessService->applyDerivationVisibility(
            ApoyoDerivacion::query()
                ->with(['destinationProfessional.staff:id,full_name', 'destinationUser:id,name', 'documents.uploadedBy:id,name'])
                ->whereIn('attention_id', $attentionIds),
            $request->user(),
        )->latest('derived_at')->get();

        $followUps = ApoyoSeguimiento::query()
            ->with(['responsibleProfessional.staff:id,full_name', 'responsibleUser:id,name', 'documents.uploadedBy:id,name'])
            ->whereIn('attention_id', $attentionIds)
            ->latest('scheduled_at')
            ->get();

        $plans = ApoyoPlan::query()
            ->with(['responsibleProfessional.staff:id,full_name', 'responsibleUser:id,name', 'actions', 'documents.uploadedBy:id,name'])
            ->where('student_profile_id', $studentProfile->id)
            ->when(
                !$this->accessService->canViewTeamAttentions($request->user()) && !$this->accessService->canViewConfidentialAttentions($request->user()),
                fn (Builder $query) => $query->where('responsible_user_id', $request->user()->id)
            )
            ->latest('start_date')
            ->get();

        $interviews = ApoyoEntrevista::query()
            ->with(['professional.staff:id,full_name', 'professionalUser:id,name', 'documents.uploadedBy:id,name'])
            ->where('student_profile_id', $studentProfile->id)
            ->when(
                !$this->accessService->canViewTeamAttentions($request->user()) && !$this->accessService->canViewConfidentialAttentions($request->user()),
                fn (Builder $query) => $query->where('professional_user_id', $request->user()->id)
            )
            ->latest('interview_at')
            ->get();

        $documents = $attentions->pluck('documents')
            ->flatten()
            ->merge($derivations->pluck('documents')->flatten())
            ->merge($followUps->pluck('documents')->flatten())
            ->merge($plans->pluck('documents')->flatten())
            ->merge($interviews->pluck('documents')->flatten())
            ->unique('id')
            ->values();

        return response()->json([
            'student' => $this->studentContextService->studentSummary($studentProfile, null, $request->user()),
            'summary' => [
                'attentions_total' => $attentions->count(),
                'derivations_total' => $derivations->count(),
                'follow_ups_total' => $followUps->count(),
                'plans_total' => $plans->count(),
                'interviews_total' => $interviews->count(),
                'documents_total' => $documents->count(),
            ],
            'attentions' => $attentions,
            'derivations' => $derivations,
            'follow_ups' => $followUps,
            'plans' => $plans,
            'interviews' => $interviews,
            'documents' => $documents,
        ]);
    }
}
