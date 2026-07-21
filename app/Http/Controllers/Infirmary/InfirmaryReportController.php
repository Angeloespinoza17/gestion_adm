<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Services\Infirmary\InfirmaryAccessService;
use App\Services\Infirmary\InfirmaryMedicationStockService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfirmaryReportController extends Controller
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
        private readonly InfirmaryMedicationStockService $stockService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewReports($request->user()) || $this->accessService->canViewModule($request->user()), 403);
        $this->stockService->refreshDynamicStatuses();

        [$from, $to] = $this->resolveRange($request);

        $attentionQuery = InfirmaryAttention::query()
            ->with(['student:id,first_name,last_name,rut', 'attendedBy:id,name'])
            ->where('subject_type', InfirmaryAttention::SUBJECT_STUDENT)
            ->whereBetween('attended_at', [$from, $to]);
        $accidentQuery = InfirmaryAccident::query()
            ->with(['student:id,first_name,last_name,rut', 'dependency:id,name'])
            ->whereBetween('occurred_at', [$from, $to]);
        $administrationQuery = InfirmaryMedicationAdministration::query()
            ->with(['student:id,first_name,last_name,rut', 'medication:id,name,commercial_name', 'administeredBy:id,name'])
            ->whereNotNull('student_profile_id')
            ->whereBetween('administered_at', [$from, $to]);
        $referralQuery = InfirmaryAttentionReferral::query()
            ->whereHas('attention', fn (Builder $attention) => $attention
                ->where('subject_type', InfirmaryAttention::SUBJECT_STUDENT))
            ->whereBetween('referred_at', [$from, $to]);
        $callQuery = InfirmaryAttentionCall::query()
            ->with(['student:id,first_name,last_name,rut', 'calledBy:id,name'])
            ->whereNotNull('student_profile_id')
            ->whereBetween('called_at', [$from, $to]);

        $this->applyFilters($request, $attentionQuery, $accidentQuery, $administrationQuery, $referralQuery, $callQuery);

        return response()->json([
            'date_range' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'period' => $request->query('period', 'mensual'),
            ],
            'summary' => [
                'attentions_total' => (clone $attentionQuery)->count(),
                'accidents_total' => (clone $accidentQuery)->count(),
                'medications_administered_total' => (clone $administrationQuery)->count(),
                'referrals_total' => (clone $referralQuery)->count(),
                'calls_total' => (clone $callQuery)->count(),
                'average_attention_minutes' => round((float) (clone $attentionQuery)->avg('attention_duration_minutes'), 1),
            ],
            'attentions_by_course' => (clone $attentionQuery)
                ->selectRaw('COALESCE(course_name_snapshot, "Sin curso") as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'attentions_by_category' => (clone $attentionQuery)
                ->selectRaw('attention_category as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'accidents_by_type' => (clone $accidentQuery)
                ->selectRaw('accident_type as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'accidents_by_dependency' => (clone $accidentQuery)
                ->leftJoin('maintenance_dependencies', 'maintenance_dependencies.id', '=', 'infirmary_accidents.dependency_id')
                ->selectRaw('COALESCE(maintenance_dependencies.name, "Sin dependencia") as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'medications_by_name' => (clone $administrationQuery)
                ->join('infirmary_medications', 'infirmary_medications.id', '=', 'infirmary_medication_administrations.medication_id')
                ->selectRaw('COALESCE(infirmary_medications.commercial_name, infirmary_medications.name) as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'referrals_by_type' => (clone $referralQuery)
                ->selectRaw('referral_type as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'calls_by_result' => (clone $callQuery)
                ->selectRaw('call_status as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'professionals' => (clone $attentionQuery)
                ->join('users', 'users.id', '=', 'infirmary_attentions.attended_by_user_id')
                ->selectRaw('users.name as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'detail_rows' => [
                'attentions' => (clone $attentionQuery)->latest('attended_at')->limit(80)->get(),
                'accidents' => (clone $accidentQuery)->latest('occurred_at')->limit(80)->get(),
                'administrations' => (clone $administrationQuery)->latest('administered_at')->limit(80)->get(),
                'calls' => (clone $callQuery)->latest('called_at')->limit(80)->get(),
            ],
        ]);
    }

    private function applyFilters(
        Request $request,
        Builder $attentionQuery,
        Builder $accidentQuery,
        Builder $administrationQuery,
        Builder $referralQuery,
        Builder $callQuery,
    ): void {
        $courseSectionId = $request->query('course_section_id');
        $studentId = $request->query('student_profile_id');
        $accidentType = trim((string) $request->query('accident_type'));
        $medicationId = $request->query('medication_id');
        $referralType = trim((string) $request->query('referral_type'));
        $professionalId = $request->query('professional_id');
        $dependencyId = $request->query('dependency_id');
        $attentionCategory = trim((string) $request->query('attention_category'));

        $attentionQuery
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($professionalId, fn ($query) => $query->where('attended_by_user_id', $professionalId))
            ->when($dependencyId, fn ($query) => $query->where('dependency_id', $dependencyId))
            ->when($attentionCategory !== '', fn ($query) => $query->where('attention_category', $attentionCategory));

        $accidentQuery
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($accidentType !== '', fn ($query) => $query->where('accident_type', $accidentType))
            ->when($dependencyId, fn ($query) => $query->where('dependency_id', $dependencyId));

        $administrationQuery
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($professionalId, fn ($query) => $query->where('administered_by_user_id', $professionalId))
            ->when($medicationId, fn ($query) => $query->where('medication_id', $medicationId));

        $referralQuery
            ->when($referralType !== '', fn ($query) => $query->where('referral_type', $referralType))
            ->when($studentId, fn ($query) => $query->whereHas('attention', fn ($inner) => $inner->where('student_profile_id', $studentId)))
            ->when($courseSectionId, fn ($query) => $query->whereHas('attention', fn ($inner) => $inner->where('course_section_id', $courseSectionId)))
            ->when($attentionCategory !== '', fn ($query) => $query->whereHas('attention', fn ($inner) => $inner->where('attention_category', $attentionCategory)));

        $callQuery
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($professionalId, fn ($query) => $query->where('called_by_user_id', $professionalId));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $period = trim((string) $request->query('period', 'mensual'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        if ($from !== '' || $to !== '') {
            return [
                $from !== '' ? Carbon::parse($from)->startOfDay() : now()->startOfMonth(),
                $to !== '' ? Carbon::parse($to)->endOfDay() : now()->endOfDay(),
            ];
        }

        return match ($period) {
            'diario' => [now()->startOfDay(), now()->endOfDay()],
            'semanal' => [now()->startOfWeek(), now()->endOfWeek()],
            'semestral' => [now()->copy()->subMonths(6)->startOfDay(), now()->endOfDay()],
            'anual' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }
}
