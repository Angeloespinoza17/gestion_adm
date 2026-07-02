<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApoyoProfesional\SaveApoyoAtencionRequest;
use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use App\Services\ApoyoProfesional\ApoyoProfesionalAttentionService;
use App\Services\ApoyoProfesional\ApoyoProfesionalStudentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApoyoProfesionalAttentionController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
        private readonly ApoyoProfesionalAttentionService $attentionService,
        private readonly ApoyoProfesionalStudentContextService $studentContextService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApoyoAtencion::class);

        $query = $this->accessService->applyAttentionVisibility(
            ApoyoAtencion::query()
                ->with([
                    'student:id,first_name,last_name,registered_name,rut',
                    'professional.staff:id,full_name',
                    'attendedBy:id,name',
                    'attentionType:id,name',
                    'motive:id,name',
                ])
                ->withCount(['derivations', 'followUps', 'documents']),
            $request->user(),
        );

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $courseSectionId = $request->query('course_section_id');
        $studentId = $request->query('student_profile_id');
        $professionalId = $request->query('attended_by_user_id');
        $attentionType = trim((string) $request->query('attention_type_label'));
        $confidentiality = trim((string) $request->query('confidentiality_level'));
        $area = trim((string) $request->query('professional_area_name'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        $query
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('student_full_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('student_rut_snapshot', 'like', "%{$search}%")
                        ->orWhere('reason_summary', 'like', "%{$search}%")
                        ->orWhere('professional_role_name', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($courseSectionId, fn ($builder) => $builder->where('course_section_id', $courseSectionId))
            ->when($studentId, fn ($builder) => $builder->where('student_profile_id', $studentId))
            ->when($professionalId, fn ($builder) => $builder->where('attended_by_user_id', $professionalId))
            ->when($attentionType !== '', fn ($builder) => $builder->where('attention_type_label', $attentionType))
            ->when($confidentiality !== '', fn ($builder) => $builder->where('confidentiality_level', $confidentiality))
            ->when($area !== '', fn ($builder) => $builder->where('professional_area_name', $area))
            ->when($from !== '', fn ($builder) => $builder->whereDate('attended_at', '>=', $from))
            ->when($to !== '', fn ($builder) => $builder->whereDate('attended_at', '<=', $to));

        return response()->json(
            $query->latest('attended_at')->paginate((int) $request->query('per_page', 12))
        );
    }

    public function store(SaveApoyoAtencionRequest $request): JsonResponse
    {
        $this->authorize('create', ApoyoAtencion::class);

        $attention = $this->attentionService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Atención registrada correctamente.',
            'data' => $attention,
            'student_context' => $this->studentContextService->studentSummary($attention->student, $attention->attended_at, $request->user()),
        ], 201);
    }

    public function show(ApoyoAtencion $attention): JsonResponse
    {
        $this->authorize('view', $attention);
        $attention->load('student');

        return response()->json([
            'data' => $attention->load([
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'teacher:id,full_name',
                'professional.staff:id,full_name',
                'attendedBy:id,name,email',
                'attentionType:id,slug,name,requires_other_description',
                'motive:id,slug,name,area_slug',
                'derivations.destinationProfessional.staff:id,full_name',
                'derivations.destinationUser:id,name',
                'followUps.responsibleProfessional.staff:id,full_name',
                'followUps.responsibleUser:id,name',
                'documents.uploadedBy:id,name',
                'closedBy:id,name',
            ]),
            'student_context' => $this->studentContextService->studentSummary($attention->student, $attention->attended_at, request()->user()),
        ]);
    }

    public function update(SaveApoyoAtencionRequest $request, ApoyoAtencion $attention): JsonResponse
    {
        $this->authorize('update', $attention);

        $updated = $this->attentionService->update($attention, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Atención actualizada correctamente.',
            'data' => $updated,
            'student_context' => $this->studentContextService->studentSummary($updated->student, $updated->attended_at, $request->user()),
        ]);
    }

    public function destroy(ApoyoAtencion $attention): JsonResponse
    {
        $this->authorize('delete', $attention);

        foreach ($attention->documents as $document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
        }

        $this->attentionService->delete($attention);

        return response()->json([
            'message' => 'Atención eliminada correctamente.',
        ]);
    }

    public function close(Request $request, ApoyoAtencion $attention): JsonResponse
    {
        abort_unless($this->accessService->canCloseCase($request->user()), 403);
        $this->authorize('update', $attention);

        $payload = $request->validate([
            'case_closed_notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Caso cerrado correctamente.',
            'data' => $this->attentionService->close($attention, $request->user(), $payload),
        ]);
    }
}
