<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspectoria\SaveInspectoriaAttentionRequest;
use App\Models\AcademicYear;
use App\Models\Inspectoria\InspectoriaAttention;
use App\Models\StudentProfile;
use App\Services\Inspectoria\InspectoriaAccessService;
use App\Services\Inspectoria\InspectoriaCodeService;
use App\Services\Inspectoria\InspectoriaPsychosocialProfessionalService;
use App\Services\Inspectoria\InspectoriaPsychosocialReferralService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectoriaAttentionController extends Controller
{
    public function __construct(
        private readonly InspectoriaAccessService $access,
        private readonly InspectoriaCodeService $codes,
        private readonly InspectoriaPsychosocialProfessionalService $psychosocialProfessionals,
        private readonly InspectoriaPsychosocialReferralService $psychosocialReferrals,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);
        $search = trim((string) $request->query('search'));
        $query = InspectoriaAttention::query()->with([
            'student:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name',
            'inspector:id,full_name', 'attendedBy:id,name', 'psychosocialReferralUser:id,name',
        ]);
        $this->access->scopeAttentions($query, $request->user());
        $query->when($search !== '', function (Builder $query) use ($search) {
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('attention_code', 'like', "%{$search}%")
                    ->orWhere('student_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('course_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('brief_note', 'like', "%{$search}%");
            });
        })->when($request->filled('date'), fn ($query) => $query->whereDate('attended_at', $request->query('date')))
            ->when($request->filled('follow_up'), fn ($query) => $query->where('requires_follow_up', $request->boolean('follow_up')));

        return response()->json($query->latest('attended_at')->paginate((int) $request->query('per_page', 15)));
    }

    public function store(SaveInspectoriaAttentionRequest $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::ATTENTIONS), 403);
        $payload = $request->validated();
        $student = StudentProfile::query()->with(['enrollments.courseSection', 'enrollments.academicYear'])->findOrFail($payload['student_profile_id']);
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $enrollment = $student->preferredEnrollment($activeYear);
        $user = $request->user();
        $hasPsychosocialReferral = in_array('derivacion_psicosocial', $payload['actions_taken'] ?? [], true);
        $referralProfessional = $hasPsychosocialReferral
            ? $this->psychosocialProfessionals->find((int) $payload['psychosocial_referral_user_id'])
            : null;
        if ($hasPsychosocialReferral && ! $referralProfessional) {
            throw ValidationException::withMessages([
                'psychosocial_referral_user_id' => 'Selecciona una psicóloga o trabajadora social activa.',
            ]);
        }
        $referralProfession = $referralProfessional
            ? $this->psychosocialProfessionals->profession($referralProfessional)
            : null;
        abort_unless($this->access->canAccessStudent($user, $student->id), 403, 'La alumna no pertenece a un curso asignado a esta inspectora.');
        if ($this->access->isCourseScoped($user)) {
            $courseIds = $this->access->assignedCourseIds($user);
            $enrollment = $student->enrollments->first(fn ($item) => $courseIds->contains((int) $item->course_section_id)
                && (! $activeYear || (int) $item->academic_year_id === (int) $activeYear->id));
        }

        $attention = DB::transaction(function () use (
            $payload,
            $enrollment,
            $user,
            $student,
            $hasPsychosocialReferral,
            $referralProfessional,
            $referralProfession,
        ) {
            $attention = InspectoriaAttention::query()->create([
                ...$payload,
                'attention_code' => $this->codes->next('ATE'),
                'course_section_id' => $enrollment?->course_section_id,
                'inspector_staff_id' => $user->staff_id,
                'attended_by_user_id' => $user->id,
                'attended_at' => $payload['attended_at'] ?? now(),
                'status' => 'registrada',
                'student_name_snapshot' => $student->registered_name_resolved,
                'course_name_snapshot' => $enrollment?->snapshot_course_display_name ?? $enrollment?->courseSection?->display_name,
                'requires_follow_up' => $hasPsychosocialReferral ? true : ($payload['requires_follow_up'] ?? false),
                'psychosocial_referral_user_id' => $referralProfessional?->id,
                'psychosocial_referral_name_snapshot' => $referralProfessional?->staff?->full_name ?: $referralProfessional?->name,
                'psychosocial_referral_role_snapshot' => $referralProfession['label'] ?? null,
                'psychosocial_referred_at' => $referralProfessional ? now() : null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($hasPsychosocialReferral) {
                $this->psychosocialReferrals->sync($attention, $user);
            }

            return $attention;
        });

        return response()->json(['message' => 'Atención registrada correctamente.', 'data' => $attention->fresh([
            'student:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name', 'inspector:id,full_name',
            'psychosocialReferralUser:id,name', 'socialWorkReferral.assignedUser:id,name',
        ])], 201);
    }
}
