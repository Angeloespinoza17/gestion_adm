<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Models\Infirmary\InfirmaryAttentionFollowUp;
use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\Infirmary\InfirmaryAttentionTreatment;
use App\Models\Infirmary\InfirmaryCatalogItem;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\MaintenanceDependency;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Infirmary\InfirmaryAccessService;
use App\Services\Infirmary\InfirmaryMedicationStockService;
use App\Services\Infirmary\InfirmaryStudentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InfirmaryCatalogController extends Controller
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
        private readonly InfirmaryMedicationStockService $stockService,
        private readonly InfirmaryStudentContextService $studentContextService,
    ) {}

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
            'dependencies' => $this->dependencyOptions(),
            'staff' => Staff::query()
                ->with('cargo:id,slug,name')
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'rut', 'cargo_id', 'institutional_email', 'phone', 'status']),
            'users' => User::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id']),
            'suppliers' => Supplier::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'business_name', 'rut']),
            'medications' => InfirmaryMedication::query()
                ->where('inventory_type', InfirmaryMedication::INVENTORY_TYPE_MEDICATION)
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'commercial_name', 'unit', 'current_stock', 'status', 'expires_at']),
            'inventory_type_options' => InfirmaryMedication::INVENTORY_TYPE_OPTIONS,
            'inventory_source_options' => InfirmaryMedication::SOURCE_TYPE_OPTIONS,
            'medication_regimen_options' => InfirmaryMedicationAuthorization::REGIMEN_OPTIONS,
            'medication_schedule_mode_options' => InfirmaryMedicationAuthorization::SCHEDULE_MODE_OPTIONS,
            'medication_dose_unit_options' => InfirmaryMedicationAuthorization::DOSE_UNIT_OPTIONS,
            'medication_route_options' => InfirmaryMedicationAuthorization::ADMINISTRATION_ROUTE_OPTIONS,
            'medication_administration_status_options' => InfirmaryMedicationAdministration::STATUS_OPTIONS,
            'medication_non_administration_reason_options' => InfirmaryMedicationAdministration::NON_ADMINISTRATION_REASON_OPTIONS,
            'medication_daily_status_options' => [
                ['value' => 'pending', 'label' => 'Pendientes o parciales'],
                ['value' => 'completed', 'label' => 'Completadas'],
                ['value' => 'exception', 'label' => 'Con incidencia'],
                ['value' => 'not_applicable', 'label' => 'No aplica hoy'],
            ],
            'attention_categories' => $this->attentionCategoryOptions(),
            'priority_options' => InfirmaryAttention::PRIORITY_OPTIONS,
            'status_options' => InfirmaryAttention::STATUS_OPTIONS,
            'accident_location_options' => InfirmaryAttention::ACCIDENT_LOCATION_OPTIONS,
            'school_insurance_certificate' => config('infirmary.school_insurance_certificate', []),
            'companion_options' => InfirmaryAttention::COMPANION_OPTIONS,
            'companion_staff' => $this->companionStaffOptions(),
            'treatment_category_options' => InfirmaryAttentionTreatment::CATEGORY_OPTIONS,
            'physical_treatment_options' => InfirmaryAttentionTreatment::PHYSICAL_TYPE_OPTIONS,
            'treatment_derivation_options' => InfirmaryAttentionTreatment::DERIVATION_TYPE_OPTIONS,
            'treatment_derivation_support_options' => InfirmaryAttentionTreatment::DERIVATION_SUPPORT_TEAM_OPTIONS,
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
                'can_manage_catalogs' => $this->accessService->canManageCatalogs($request->user()),
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

    public function studentContext(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => $this->studentContextService->studentSummary($studentProfile),
        ]);
    }

    private function attentionCategoryOptions(): array
    {
        if (Schema::hasTable('infirmary_catalog_items')) {
            $items = InfirmaryCatalogItem::optionsForGroup(InfirmaryCatalogItem::GROUP_ATTENTION_CATEGORY);

            if ($items !== []) {
                return $items;
            }
        }

        return [
            ['value' => 'accidente_menor', 'label' => 'Accidente menor (caída o golpe)'],
            ['value' => 'accidente_mayor', 'label' => 'Accidente mayor (herida, contusión o torcedura)'],
            ['value' => 'emocional', 'label' => 'Emocional'],
            ['value' => 'dolor_estomago', 'label' => 'Dolor de estómago'],
            ['value' => 'dolor_cabeza', 'label' => 'Dolor de cabeza'],
            ['value' => 'epistaxis', 'label' => 'Epistaxis'],
            ['value' => 'control_signos_vitales', 'label' => 'Control de signos vitales'],
            ['value' => 'herido_dolor_anterior', 'label' => 'Herido o dolor anterior'],
            ['value' => 'otro', 'label' => 'Otro'],
        ];
    }

    private function dependencyOptions()
    {
        $query = MaintenanceDependency::query()
            ->physicalSpaces()
            ->where('active', true);

        if (! Schema::hasTable('infirmary_attentions')) {
            return $query
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'usage', 'location', 'floor_sector'])
                ->map(fn ($dependency) => $this->dependencyPayload($dependency));
        }

        $usageQuery = DB::table('infirmary_attentions')
            ->select('dependency_id', DB::raw('COUNT(*) as attentions_count'))
            ->whereNotNull('dependency_id')
            ->groupBy('dependency_id');

        return $query
            ->leftJoinSub($usageQuery, 'attention_dependency_usage', function ($join) {
                $join->on('maintenance_dependencies.id', '=', 'attention_dependency_usage.dependency_id');
            })
            ->orderByDesc(DB::raw('COALESCE(attention_dependency_usage.attentions_count, 0)'))
            ->orderBy('maintenance_dependencies.name')
            ->get([
                'maintenance_dependencies.id',
                'maintenance_dependencies.code',
                'maintenance_dependencies.name',
                'maintenance_dependencies.usage',
                'maintenance_dependencies.location',
                'maintenance_dependencies.floor_sector',
                DB::raw('COALESCE(attention_dependency_usage.attentions_count, 0) as attentions_count'),
            ])
            ->map(fn ($dependency) => $this->dependencyPayload($dependency));
    }

    private function companionStaffOptions(): array
    {
        $cargoSlugToType = [];

        foreach (InfirmaryAttention::STAFF_COMPANION_CARGO_SLUGS as $type => $slugs) {
            foreach ($slugs as $slug) {
                $cargoSlugToType[$slug] = $type;
            }
        }

        $items = Staff::query()
            ->with('cargo:id,slug,name')
            ->where('active', true)
            ->whereHas('cargo', fn ($query) => $query->whereIn('slug', array_keys($cargoSlugToType)))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'cargo_id', 'institutional_email'])
            ->groupBy(fn (Staff $staff) => $cargoSlugToType[$staff->cargo?->slug] ?? null)
            ->map(fn ($staff) => $staff->map(fn (Staff $item) => [
                'id' => $item->id,
                'full_name' => $item->full_name,
                'cargo_name' => $item->cargo?->name,
                'cargo_slug' => $item->cargo?->slug,
                'institutional_email' => $item->institutional_email,
            ])->values()->all())
            ->all();

        foreach (array_keys(InfirmaryAttention::STAFF_COMPANION_CARGO_SLUGS) as $type) {
            $items[$type] ??= [];
        }

        return $items;
    }

    private function dependencyPayload($dependency): array
    {
        $usage = trim((string) ($dependency->usage ?? ''));
        $count = (int) ($dependency->attentions_count ?? 0);

        return [
            'id' => $dependency->id,
            'code' => $dependency->code,
            'name' => $dependency->name,
            'usage' => $usage !== '' ? $usage : null,
            'location' => $dependency->location,
            'floor_sector' => $dependency->floor_sector,
            'attentions_count' => $count,
            'label' => $usage !== ''
                ? "{$dependency->name} · {$usage}"
                : $dependency->name,
        ];
    }
}
