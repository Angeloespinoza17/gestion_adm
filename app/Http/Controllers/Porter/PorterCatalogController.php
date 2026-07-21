<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\EducationLevel;
use App\Models\MaintenanceDependency;
use App\Models\PorterAuthorizationRequest;
use App\Models\PorterDailyLogEntry;
use App\Models\PorterExternalServiceEntry;
use App\Models\PorterGoodsMovement;
use App\Models\PorterKeyLoan;
use App\Models\PorterReceivedItem;
use App\Models\PorterStudentWithdrawal;
use App\Models\PorterVisit;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Services\Porter\PorterAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PorterCatalogController extends Controller
{
    public function __construct(
        private readonly PorterAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $academicYears = AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active']);
        $activeAcademicYearId = $academicYears->firstWhere('is_active', true)?->id;

        return response()->json([
            'academic_years' => $academicYears,
            'active_academic_year_id' => $activeAcademicYearId,
            'courses' => CourseSection::query()
                ->when($activeAcademicYearId, fn ($query) => $query->where('academic_year_id', $activeAcademicYearId))
                ->where('active', true)
                ->orderBy('display_name')
                ->get(['id', 'academic_year_id', 'education_level_id', 'display_name', 'section_name']),
            'education_levels' => EducationLevel::query()->orderBy('order')->get(['id', 'name', 'order', 'type']),
            'departments' => Department::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'color']),
            'staff' => Staff::query()->where('active', true)->orderBy('full_name')->get(['id', 'full_name', 'rut', 'cargo_id']),
            'dependencies' => MaintenanceDependency::query()
                ->physicalSpaces()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'responsible_staff_id']),
            'student_general_statuses' => StudentProfile::GENERAL_STATUS_OPTIONS,
            'student_enrollment_statuses' => StudentEnrollment::STATUS_OPTIONS,
            'withdrawal_statuses' => PorterStudentWithdrawal::STATUS_OPTIONS,
            'withdrawal_relationships' => PorterStudentWithdrawal::RELATIONSHIP_OPTIONS,
            'withdrawal_reasons' => PorterStudentWithdrawal::REASON_OPTIONS,
            'received_item_types' => PorterReceivedItem::ITEM_TYPE_OPTIONS,
            'received_item_statuses' => PorterReceivedItem::STATUS_OPTIONS,
            'received_item_recipient_types' => PorterReceivedItem::RECIPIENT_TYPE_OPTIONS,
            'goods_movement_types' => PorterGoodsMovement::MOVEMENT_TYPE_OPTIONS,
            'goods_statuses' => PorterGoodsMovement::STATUS_OPTIONS,
            'goods_document_types' => PorterGoodsMovement::DOCUMENT_TYPE_OPTIONS,
            'visit_statuses' => PorterVisit::STATUS_OPTIONS,
            'external_service_statuses' => PorterExternalServiceEntry::STATUS_OPTIONS,
            'daily_log_categories' => PorterDailyLogEntry::CATEGORY_OPTIONS,
            'daily_log_priorities' => PorterDailyLogEntry::PRIORITY_OPTIONS,
            'daily_log_statuses' => PorterDailyLogEntry::STATUS_OPTIONS,
            'key_loan_statuses' => PorterKeyLoan::STATUS_OPTIONS,
            'authorization_statuses' => PorterAuthorizationRequest::STATUS_OPTIONS,
            'capabilities' => [
                'can_authorize_special_withdrawal' => $this->accessService->canAuthorizeSpecialWithdrawal($request->user()),
                'can_export' => $this->accessService->canExport($request->user()),
            ],
        ]);
    }
}
