<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaExternalInstitution;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Staff;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaStudentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaCatalogController extends Controller
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
        private readonly ConvivenciaStudentContextService $studentContextService,
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
                ->with('educationLevel:id,name')
                ->when($activeAcademicYearId, fn ($query) => $query->where('academic_year_id', $activeAcademicYearId))
                ->orderBy('display_name')
                ->get(['id', 'academic_year_id', 'education_level_id', 'display_name', 'section_name']),
            'students' => $this->studentContextService->studentOptions(),
            'staff' => Staff::query()
                ->with('cargo:id,name,slug')
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'institutional_email', 'cargo_id']),
            'users' => User::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id']),
            'departments' => Department::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'color']),
            'external_institutions' => ConvivenciaExternalInstitution::query()
                ->where('active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get(['id', 'category', 'name', 'contact_name', 'contact_email', 'contact_phone']),
            'catalogs' => ConvivenciaCatalogItem::query()
                ->where('active', true)
                ->orderBy('group')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'group', 'code', 'name', 'description', 'color', 'metadata'])
                ->groupBy('group'),
            'case_status_options' => \App\Models\Convivencia\ConvivenciaCase::STATUS_OPTIONS,
            'case_origin_options' => \App\Models\Convivencia\ConvivenciaCase::ORIGIN_OPTIONS,
            'person_type_options' => \App\Models\Convivencia\ConvivenciaCase::PERSON_TYPE_OPTIONS,
            'person_role_options' => \App\Models\Convivencia\ConvivenciaCase::PERSON_ROLE_OPTIONS,
            'derivation_status_options' => \App\Models\Convivencia\ConvivenciaDerivation::STATUS_OPTIONS,
            'derivation_priority_options' => \App\Models\Convivencia\ConvivenciaDerivation::PRIORITY_OPTIONS,
            'derivation_scope_options' => \App\Models\Convivencia\ConvivenciaDerivation::SCOPE_OPTIONS,
            'plan_status_options' => \App\Models\Convivencia\ConvivenciaPlan::STATUS_OPTIONS,
            'plan_action_type_options' => \App\Models\Convivencia\ConvivenciaPlanAction::TYPE_OPTIONS,
            'protocol_status_options' => \App\Models\Convivencia\ConvivenciaProtocol::STATUS_OPTIONS,
            'protocol_activation_status_options' => \App\Models\Convivencia\ConvivenciaProtocolActivation::STATUS_OPTIONS,
            'measure_status_options' => \App\Models\Convivencia\ConvivenciaMeasure::STATUS_OPTIONS,
            'interview_follow_up_status_options' => \App\Models\Convivencia\ConvivenciaInterview::FOLLOW_UP_STATUS_OPTIONS,
            'daily_log_status_options' => \App\Models\Convivencia\ConvivenciaDailyLog::STATUS_OPTIONS,
            'sociogram_status_options' => \App\Models\Convivencia\ConvivenciaSociogram::STATUS_OPTIONS,
            'complaint_status_options' => \App\Models\Convivencia\ConvivenciaComplaint::STATUS_OPTIONS,
            'complaint_type_options' => \App\Models\Convivencia\ConvivenciaComplaint::COMPLAINANT_TYPE_OPTIONS,
            'idps_scope_options' => \App\Models\Convivencia\ConvivenciaIdpsResult::SCOPE_OPTIONS,
            'attachment_categories' => \App\Models\Convivencia\ConvivenciaAttachment::CATEGORY_OPTIONS,
            'capabilities' => [
                'can_view_dashboard' => $this->accessService->canViewDashboard($request->user()),
                'can_manage_plans' => $this->accessService->canManagePlans($request->user()),
                'can_create_cases' => $this->accessService->canCreateCase($request->user()),
                'can_view_cases' => $this->accessService->canViewCases($request->user()),
                'can_edit_cases' => $this->accessService->canEditCases($request->user()),
                'can_close_cases' => $this->accessService->canCloseCases($request->user()),
                'can_view_sensitive' => $this->accessService->canViewSensitiveData($request->user()),
                'can_manage_complaints' => $this->accessService->canManageComplaints($request->user()),
                'can_manage_protocols' => $this->accessService->canManageProtocols($request->user()),
                'can_activate_protocols' => $this->accessService->canActivateProtocols($request->user()),
                'can_manage_interviews' => $this->accessService->canManageInterviews($request->user()),
                'can_manage_measures' => $this->accessService->canManageMeasures($request->user()),
                'can_manage_internal_derivations' => $this->accessService->canManageInternalDerivations($request->user()),
                'can_manage_external_derivations' => $this->accessService->canManageExternalDerivations($request->user()),
                'can_view_sociograms' => $this->accessService->canViewSociograms($request->user()),
                'can_manage_sociograms' => $this->accessService->canManageSociograms($request->user()),
                'can_view_course_reports' => $this->accessService->canViewCourseReports($request->user()),
                'can_manage_daily_logs' => $this->accessService->canManageDailyLogs($request->user()),
                'can_export_reports' => $this->accessService->canExportReports($request->user()),
                'can_manage_settings' => $this->accessService->canManageSettings($request->user()),
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

    public function storeCatalogItem(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManageSettings($request->user()), 403);

        $payload = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'group' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:30'],
            'metadata' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $item = ConvivenciaCatalogItem::query()->updateOrCreate(
            ['group' => $payload['group'], 'code' => $payload['code']],
            array_merge($payload, [
                'active' => $payload['active'] ?? true,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]),
        );

        return response()->json([
            'message' => 'Ítem de catálogo guardado correctamente.',
            'data' => $item,
        ], 201);
    }

    public function updateCatalogItem(Request $request, ConvivenciaCatalogItem $catalogItem): JsonResponse
    {
        abort_unless($this->accessService->canManageSettings($request->user()), 403);

        $payload = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'group' => ['sometimes', 'string', 'max:80'],
            'code' => ['sometimes', 'string', 'max:80'],
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:30'],
            'metadata' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $catalogItem->fill(array_merge($payload, [
            'updated_by' => $request->user()->id,
        ]))->save();

        return response()->json([
            'message' => 'Ítem de catálogo actualizado correctamente.',
            'data' => $catalogItem->fresh(),
        ]);
    }

    public function storeInstitution(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManageSettings($request->user()), 403);

        $payload = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $institution = ConvivenciaExternalInstitution::query()->create(array_merge($payload, [
            'active' => $payload['active'] ?? true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Institución externa registrada correctamente.',
            'data' => $institution,
        ], 201);
    }

    public function updateInstitution(Request $request, ConvivenciaExternalInstitution $externalInstitution): JsonResponse
    {
        abort_unless($this->accessService->canManageSettings($request->user()), 403);

        $payload = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'name' => ['sometimes', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $externalInstitution->fill(array_merge($payload, [
            'updated_by' => $request->user()->id,
        ]))->save();

        return response()->json([
            'message' => 'Institución externa actualizada correctamente.',
            'data' => $externalInstitution->fresh(),
        ]);
    }
}
