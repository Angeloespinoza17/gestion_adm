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
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        $query = InfirmaryAttention::query()
            ->with([
                'student:id,first_name,last_name,registered_name,rut',
                'dependency:id,name',
                'teacher:id,full_name',
                'referredBy:id,full_name',
            ])
            ->withCount(['referrals', 'calls', 'followUps', 'documents', 'accidents'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('student_full_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('student_rut_snapshot', 'like', "%{$search}%")
                        ->orWhere('consultation_reason', 'like', "%{$search}%")
                        ->orWhere('initial_description', 'like', "%{$search}%");
                });
            })
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($category !== '', fn ($query) => $query->where('attention_category', $category))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->when($from !== '', fn ($query) => $query->whereDate('attended_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('attended_at', '<=', $to));

        return response()->json(
            $query->latest('attended_at')->paginate((int) $request->query('per_page', 12))
        );
    }

    public function store(SaveInfirmaryAttentionRequest $request): JsonResponse
    {
        $this->authorize('create', InfirmaryAttention::class);

        $attention = $this->attentionService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Atención registrada correctamente.',
            'data' => $attention,
            'student_context' => $this->studentContextService->studentSummary($attention->student),
        ], 201);
    }

    public function show(InfirmaryAttention $attention): JsonResponse
    {
        $this->authorize('view', $attention);
        $attention->load('student');

        return response()->json([
            'data' => $attention->load([
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'teacher:id,full_name',
                'referredBy:id,full_name',
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
            'student_context' => $this->studentContextService->studentSummary($attention->student, $attention->attended_at),
        ]);
    }

    public function update(SaveInfirmaryAttentionRequest $request, InfirmaryAttention $attention): JsonResponse
    {
        $this->authorize('update', $attention);

        $updated = $this->attentionService->update($attention, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Atención actualizada correctamente.',
            'data' => $updated,
            'student_context' => $this->studentContextService->studentSummary($updated->student, $updated->attended_at),
        ]);
    }

    public function destroy(InfirmaryAttention $attention): JsonResponse
    {
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
}
