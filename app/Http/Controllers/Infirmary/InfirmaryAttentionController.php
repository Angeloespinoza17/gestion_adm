<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infirmary\SaveInfirmaryAttentionRequest;
use App\Models\Infirmary\InfirmaryAttention;
use App\Services\Infirmary\InfirmaryAttentionService;
use App\Services\Infirmary\InfirmaryStudentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InfirmaryAttentionController extends Controller
{
    public function __construct(
        private readonly InfirmaryAttentionService $attentionService,
        private readonly InfirmaryStudentContextService $studentContextService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InfirmaryAttention::class);

        $search = trim((string) $request->query('search'));
        $studentId = $request->query('student_profile_id');
        $category = trim((string) $request->query('attention_category'));
        $status = trim((string) $request->query('status'));
        $priority = trim((string) $request->query('priority'));
        $courseSectionId = $request->query('course_section_id');
        $schoolInsurance = $request->boolean('school_insurance');
        $accidentLocationType = trim((string) $request->query('accident_location_type'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));
        $correlativeSearch = preg_replace('/^n[°ºo]?\s*/iu', '', $search);
        $correlativeSearch = ctype_digit((string) $correlativeSearch)
            ? (int) $correlativeSearch
            : null;

        $query = InfirmaryAttention::query()
            ->where('subject_type', InfirmaryAttention::SUBJECT_STUDENT)
            ->with([
                'student:id,first_name,last_name,registered_name,rut',
                'dependency:id,name',
                'teacher:id,full_name',
                'referredBy:id,full_name',
                'accompaniedByStaff:id,full_name,cargo_id',
                'treatments:id,attention_id,treatment_categories,derivation_type,derivation_support_teams',
                'referrals:id,attention_id,referral_type,referred_at,result',
            ])
            ->withCount(['referrals', 'calls', 'followUps', 'documents', 'accidents'])
            ->when($search !== '', function ($query) use ($search, $correlativeSearch) {
                $query->where(function ($inner) use ($search, $correlativeSearch) {
                    $inner
                        ->where('student_full_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('student_rut_snapshot', 'like', "%{$search}%")
                        ->orWhere('consultation_reason', 'like', "%{$search}%")
                        ->orWhere('logbook', 'like', "%{$search}%")
                        ->orWhere('initial_description', 'like', "%{$search}%");

                    if ($correlativeSearch !== null) {
                        $inner->orWhere('correlative_number', $correlativeSearch);
                    }
                });
            })
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($category !== '', fn ($query) => $query->where('attention_category', $category))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->when($schoolInsurance, fn ($query) => $query->whereIn('attention_category', ['accidente_menor', 'accidente_mayor']))
            ->when($accidentLocationType !== '', fn ($query) => $query->where('accident_location_type', $accidentLocationType))
            ->when($from !== '', fn ($query) => $query->whereDate($schoolInsurance ? 'occurred_at' : 'attended_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate($schoolInsurance ? 'occurred_at' : 'attended_at', '<=', $to));

        return response()->json(
            $query->latest('occurred_at')->latest('attended_at')->paginate((int) $request->query('per_page', 12))
        );
    }

    public function store(SaveInfirmaryAttentionRequest $request): JsonResponse
    {
        $this->authorize('create', InfirmaryAttention::class);

        $attention = $this->attentionService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Atención registrada correctamente.',
            'data' => $attention,
            'student_context' => $this->studentContextService->studentSummary($attention->student, $attention->occurred_at ?? $attention->attended_at),
        ], 201);
    }

    public function show(InfirmaryAttention $attention): JsonResponse
    {
        $this->ensureStudentAttention($attention);
        $this->authorize('view', $attention);
        $attention->load('student');

        return response()->json([
            'data' => $attention->load([
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'teacher:id,full_name',
                'referredBy:id,full_name',
                'accompaniedByStaff:id,full_name,cargo_id',
                'dependency:id,code,name,location,floor_sector',
                'attendedBy:id,name',
                'treatments.medication:id,name,commercial_name,unit',
                'treatments.emotionalProfessional:id,full_name',
                'referrals.responsibleUser:id,name',
                'calls.calledBy:id,name',
                'followUps.responsibleUser:id,name',
                'administrations.medication:id,name,commercial_name,unit',
                'accidents',
                'documents.uploadedBy:id,name',
            ]),
            'student_context' => $this->studentContextService->studentSummary($attention->student, $attention->occurred_at ?? $attention->attended_at),
        ]);
    }

    public function update(SaveInfirmaryAttentionRequest $request, InfirmaryAttention $attention): JsonResponse
    {
        $this->ensureStudentAttention($attention);
        $this->authorize('update', $attention);

        $updated = $this->attentionService->update($attention, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Atención actualizada correctamente.',
            'data' => $updated,
            'student_context' => $this->studentContextService->studentSummary($updated->student, $updated->occurred_at ?? $updated->attended_at),
        ]);
    }

    public function destroy(InfirmaryAttention $attention): JsonResponse
    {
        $this->ensureStudentAttention($attention);
        $this->authorize('delete', $attention);

        foreach ($attention->documents as $document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->delete();
        }

        $this->attentionService->delete($attention, request()->user());

        return response()->json([
            'message' => 'Atención eliminada correctamente.',
        ]);
    }

    public function finalize(Request $request, InfirmaryAttention $attention): JsonResponse
    {
        $this->ensureStudentAttention($attention);
        $this->authorize('update', $attention);

        $payload = $request->validate([
            'attention_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'observations' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Atención finalizada correctamente.',
            'data' => $this->attentionService->finalize($attention, $request->user(), $payload),
        ]);
    }

    private function ensureStudentAttention(InfirmaryAttention $attention): void
    {
        abort_unless($attention->subject_type === InfirmaryAttention::SUBJECT_STUDENT, 404);
    }
}
