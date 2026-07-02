<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\MaintenanceDependency;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Models\Infirmary\InfirmaryAttentionFollowUp;
use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\Infirmary\InfirmaryAttentionTreatment;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryMedication;
use App\Services\Infirmary\InfirmaryAccessService;
use App\Services\Infirmary\InfirmaryMedicationStockService;
use App\Services\Infirmary\InfirmaryStudentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfirmaryCatalogController extends Controller
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
        private readonly InfirmaryMedicationStockService $stockService,
        private readonly InfirmaryStudentContextService $studentContextService,
    ) {
    }

    public function catalogs(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);
        $this->stockService->refreshDynamicStatuses();

        $academicYears = AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active', 'is_closed']);
        $activeAcademicYearId = $academicYears->firstWhere('is_active', true)?->id;

        return response()->json([
            'academic_years' => $academicYears,
            'active_academic_year_id' => $activeAcademicYearId,
            'courses' => CourseSection::query()
                ->when($activeAcademicYearId, fn ($query) => $query->where('academic_year_id', $activeAcademicYearId))
                ->orderBy('display_name')
                ->get(['id', 'academic_year_id', 'display_name', 'section_name']),
            'dependencies' => MaintenanceDependency::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'location', 'floor_sector']),
            'staff' => Staff::query()
                ->with('cargo:id,slug,name')
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'cargo_id', 'institutional_email']),
            'users' => User::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id']),
            'suppliers' => Supplier::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'business_name', 'rut']),
            'medications' => InfirmaryMedication::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'commercial_name', 'unit', 'current_stock', 'status', 'expires_at']),
            'attention_categories' => [
                'accidente_escolar',
                'malestar_general',
                'control_signos_vitales',
                'administracion_medicamento',
                'curacion',
                'derivacion',
                'contencion_emocional',
                'control_cronico',
                'otro',
            ],
            'priority_options' => InfirmaryAttention::PRIORITY_OPTIONS,
            'status_options' => InfirmaryAttention::STATUS_OPTIONS,
            'companion_options' => InfirmaryAttention::COMPANION_OPTIONS,
            'treatment_type_options' => InfirmaryAttentionTreatment::TYPE_OPTIONS,
            'referral_options' => InfirmaryAttentionReferral::TYPE_OPTIONS,
            'call_status_options' => InfirmaryAttentionCall::STATUS_OPTIONS,
            'follow_up_status_options' => InfirmaryAttentionFollowUp::STATUS_OPTIONS,
            'accident_severity_options' => InfirmaryAccident::SEVERITY_OPTIONS,
            'accident_status_options' => InfirmaryAccident::STATUS_OPTIONS,
            'report_period_options' => [
                ['value' => 'diario', 'label' => 'Diario'],
                ['value' => 'semanal', 'label' => 'Semanal'],
                ['value' => 'mensual', 'label' => 'Mensual'],
                ['value' => 'semestral', 'label' => 'Semestral'],
                ['value' => 'anual', 'label' => 'Anual'],
            ],
            'document_categories' => [
                'pdf',
                'imagen',
                'fotografia',
                'certificado_medico',
                'receta',
                'informe_medico',
                'orden_atencion',
                'autorizacion_medica',
                'autorizacion_apoderado',
                'otro',
            ],
            'capabilities' => [
                'can_create_attention' => $this->accessService->canCreateAttention($request->user()),
                'can_edit_attention' => $this->accessService->canEditAttention($request->user()),
                'can_delete_attention' => $this->accessService->canDeleteAttention($request->user()),
                'can_manage_inventory' => $this->accessService->canManageInventory($request->user()),
                'can_manage_medications' => $this->accessService->canManageMedication($request->user()),
                'can_manage_accidents' => $this->accessService->canManageAccidents($request->user()),
                'can_view_reports' => $this->accessService->canViewReports($request->user()),
                'can_export' => $this->accessService->canExport($request->user()),
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
                : $this->studentContextService->searchPayload($search, $courseSectionId ? (int) $courseSectionId : null),
        ]);
    }
}
