<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ApoyoProfesional\ApoyoConfigAttentionType;
use App\Models\ApoyoProfesional\ApoyoConfigMotivo;
use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoAdjunto;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoEntrevista;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\ApoyoProfesional\ApoyoProfesionalProfile;
use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Models\CourseSection;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use App\Services\ApoyoProfesional\ApoyoProfesionalStudentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApoyoProfesionalCatalogController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
        private readonly ApoyoProfesionalStudentContextService $studentContextService,
    ) {
    }

    public function catalogs(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $academicYears = AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active', 'is_closed']);
        $activeAcademicYearId = $academicYears->firstWhere('is_active', true)?->id;

        return response()->json([
            'academic_years' => $academicYears,
            'active_academic_year_id' => $activeAcademicYearId,
            'courses' => CourseSection::query()
                ->when($activeAcademicYearId, fn ($query) => $query->where('academic_year_id', $activeAcademicYearId))
                ->orderBy('display_name')
                ->get(['id', 'academic_year_id', 'display_name', 'section_name']),
            'professionals' => ApoyoProfesionalProfile::query()
                ->with(['user:id,name,email', 'staff:id,full_name,institutional_email'])
                ->where('active', true)
                ->orderBy('area_name')
                ->orderBy('professional_role_name')
                ->get(['id', 'user_id', 'staff_id', 'area_slug', 'area_name', 'professional_role_slug', 'professional_role_name', 'can_receive_derivations', 'can_manage_confidential_cases']),
            'attention_types' => ApoyoConfigAttentionType::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'requires_other_description']),
            'motives' => ApoyoConfigMotivo::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'area_slug']),
            'area_options' => ApoyoProfesionalProfile::AREA_OPTIONS,
            'modality_options' => ApoyoAtencion::MODALITY_OPTIONS,
            'origin_options' => ApoyoAtencion::ORIGIN_OPTIONS,
            'priority_options' => ApoyoAtencion::PRIORITY_OPTIONS,
            'confidentiality_options' => ApoyoAtencion::CONFIDENTIALITY_OPTIONS,
            'attention_status_options' => ApoyoAtencion::STATUS_OPTIONS,
            'derivation_status_options' => ApoyoDerivacion::STATUS_OPTIONS,
            'derivation_urgency_options' => ApoyoDerivacion::URGENCY_OPTIONS,
            'follow_up_status_options' => ApoyoSeguimiento::STATUS_OPTIONS,
            'plan_status_options' => ApoyoPlan::STATUS_OPTIONS,
            'interview_type_options' => ApoyoEntrevista::TYPE_OPTIONS,
            'document_categories' => ApoyoAdjunto::CATEGORY_OPTIONS,
            'report_period_options' => [
                ['value' => 'diario', 'label' => 'Diario'],
                ['value' => 'semanal', 'label' => 'Semanal'],
                ['value' => 'mensual', 'label' => 'Mensual'],
                ['value' => 'semestral', 'label' => 'Semestral'],
                ['value' => 'anual', 'label' => 'Anual'],
            ],
            'capabilities' => [
                'can_create_attention' => $this->accessService->canCreateAttention($request->user()),
                'can_view_team' => $this->accessService->canViewTeamAttentions($request->user()),
                'can_view_confidential' => $this->accessService->canViewConfidentialAttentions($request->user()),
                'can_edit_any' => $this->accessService->canEditAnyAttention($request->user()),
                'can_delete_attention' => $this->accessService->canDeleteAttention($request->user()),
                'can_create_derivation' => $this->accessService->canCreateDerivation($request->user()),
                'can_respond_derivation' => $this->accessService->canRespondDerivation($request->user()),
                'can_create_follow_up' => $this->accessService->canCreateFollowUp($request->user()),
                'can_close_case' => $this->accessService->canCloseCase($request->user()),
                'can_create_plan' => $this->accessService->canCreatePlan($request->user()),
                'can_view_reports' => $this->accessService->canViewReports($request->user()),
                'can_export_reports' => $this->accessService->canExportReports($request->user()),
                'can_manage_configuration' => $this->accessService->canManageConfiguration($request->user()),
            ],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $search = trim((string) $request->query('search'));
        $courseSectionId = $request->query('course_section_id');

        return response()->json([
            'data' => $search === ''
                ? []
                : $this->studentContextService->searchPayload(
                    $search,
                    $courseSectionId ? (int) $courseSectionId : null,
                    12,
                    $request->user(),
                ),
        ]);
    }
}
