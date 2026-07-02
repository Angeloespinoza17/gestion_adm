<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Models\Infirmary\InfirmaryDocument;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Services\Infirmary\InfirmaryAccessService;
use App\Services\Infirmary\InfirmaryStudentContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfirmaryStudentHistoryController extends Controller
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
        private readonly InfirmaryStudentContextService $studentContextService,
    ) {
    }

    public function __invoke(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $academicYearId = $request->query('academic_year_id');
        $courseSectionId = $request->query('course_section_id');
        $category = trim((string) $request->query('attention_category'));
        $medicationId = $request->query('medication_id');
        $referralType = trim((string) $request->query('referral_type'));
        $accidentOnly = filter_var($request->query('accident_only'), FILTER_VALIDATE_BOOLEAN);

        $attentions = InfirmaryAttention::query()
            ->with(['referrals', 'calls', 'followUps', 'documents', 'administrations.medication'])
            ->where('student_profile_id', $studentProfile->id)
            ->when($academicYearId, fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->when($courseSectionId, fn (Builder $query) => $query->where('course_section_id', $courseSectionId))
            ->when($category !== '', fn (Builder $query) => $query->where('attention_category', $category))
            ->when($medicationId, fn (Builder $query) => $query->whereHas('administrations', fn (Builder $inner) => $inner->where('medication_id', $medicationId)))
            ->when($referralType !== '', fn (Builder $query) => $query->whereHas('referrals', fn (Builder $inner) => $inner->where('referral_type', $referralType)))
            ->when($accidentOnly, fn (Builder $query) => $query->whereHas('accidents'))
            ->latest('attended_at')
            ->get();

        $accidents = InfirmaryAccident::query()
            ->with(['dependency:id,name', 'courseSection:id,display_name', 'documents'])
            ->where('student_profile_id', $studentProfile->id)
            ->when($academicYearId, fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->when($courseSectionId, fn (Builder $query) => $query->where('course_section_id', $courseSectionId))
            ->latest('occurred_at')
            ->get();

        $administrations = InfirmaryMedicationAdministration::query()
            ->with(['medication:id,name,commercial_name,unit', 'administeredBy:id,name'])
            ->where('student_profile_id', $studentProfile->id)
            ->when($medicationId, fn (Builder $query) => $query->where('medication_id', $medicationId))
            ->latest('administered_at')
            ->get();

        $calls = InfirmaryAttentionCall::query()
            ->with(['calledBy:id,name'])
            ->where('student_profile_id', $studentProfile->id)
            ->latest('called_at')
            ->get();

        $documents = InfirmaryDocument::query()
            ->with('uploadedBy:id,name')
            ->where('student_profile_id', $studentProfile->id)
            ->latest('created_at')
            ->get();

        $authorizations = InfirmaryMedicationAuthorization::query()
            ->with(['medication:id,name,commercial_name,unit', 'documents'])
            ->where('student_profile_id', $studentProfile->id)
            ->latest('start_date')
            ->get();

        return response()->json([
            'student' => $this->studentContextService->studentSummary($studentProfile),
            'filters' => [
                'academic_year_id' => $academicYearId,
                'course_section_id' => $courseSectionId,
                'attention_category' => $category,
                'medication_id' => $medicationId,
                'referral_type' => $referralType,
                'accident_only' => $accidentOnly,
            ],
            'summary' => [
                'attentions_total' => $attentions->count(),
                'accidents_total' => $accidents->count(),
                'administrations_total' => $administrations->count(),
                'calls_total' => $calls->count(),
                'documents_total' => $documents->count(),
                'authorizations_total' => $authorizations->count(),
            ],
            'attentions' => $attentions,
            'accidents' => $accidents,
            'administrations' => $administrations,
            'calls' => $calls,
            'documents' => $documents,
            'authorizations' => $authorizations,
        ]);
    }
}
