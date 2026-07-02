<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaCaseRequest;
use App\Models\Convivencia\ConvivenciaCase;
use App\Services\Convivencia\ConvivenciaCaseService;
use App\Services\Convivencia\ConvivenciaStudentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaCaseController extends Controller
{
    public function __construct(
        private readonly ConvivenciaCaseService $caseService,
        private readonly ConvivenciaStudentContextService $studentContextService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaCase::class);

        $query = app(\App\Services\Convivencia\ConvivenciaAccessService::class)
            ->applyCaseVisibility(
                ConvivenciaCase::query()
                    ->with([
                        'student:id,first_name,last_name,registered_name,rut',
                        'courseSection:id,display_name',
                        'responsibleUser:id,name',
                        'responsibleStaff:id,full_name',
                    ])
                    ->withCount(['people', 'followUps', 'derivations', 'measures', 'interviews', 'protocolActivations']),
                $request->user(),
            );

        $search = trim((string) $request->query('search'));
        $query
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('folio', 'like', "%{$search}%")
                        ->orWhere('initial_report', 'like', "%{$search}%")
                        ->orWhere('classification_label', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($studentQuery) use ($search) {
                            $studentQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('registered_name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('course_section_id'), fn ($builder, $value) => $builder->where('course_section_id', $value))
            ->when($request->query('student_profile_id'), fn ($builder, $value) => $builder->where('student_profile_id', $value))
            ->when($request->query('classification_label'), fn ($builder, $value) => $builder->where('classification_label', $value))
            ->when($request->query('criticality_label'), fn ($builder, $value) => $builder->where('criticality_label', $value))
            ->when($request->query('responsible_user_id'), fn ($builder, $value) => $builder->where('responsible_user_id', $value))
            ->when($request->query('from'), fn ($builder, $value) => $builder->whereDate('opened_at', '>=', $value))
            ->when($request->query('to'), fn ($builder, $value) => $builder->whereDate('opened_at', '<=', $value));

        return response()->json($query->latest('opened_at')->paginate((int) $request->query('per_page', 12)));
    }

    public function store(SaveConvivenciaCaseRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaCase::class);

        $case = $this->caseService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Caso registrado correctamente.',
            'data' => $case,
            'student_context' => $case->student
                ? $this->studentContextService->studentSummary($case->student, $case->opened_at, $request->user())
                : null,
        ], 201);
    }

    public function show(ConvivenciaCase $case): JsonResponse
    {
        $this->authorize('view', $case);

        $case->load('student');

        return response()->json([
            'data' => $case->load([
                'academicYear:id,name,year',
                'courseSection:id,display_name,education_level_id',
                'courseSection.educationLevel:id,name',
                'student:id,first_name,last_name,registered_name,rut,guardian_name,guardian_phone,guardian_email',
                'caseType:id,name',
                'classification:id,name',
                'subclassification:id,name',
                'criticality:id,name,color',
                'responsibleUser:id,name,email',
                'responsibleStaff:id,full_name',
                'closedBy:id,name',
                'people.student:id,first_name,last_name,registered_name,rut',
                'people.user:id,name',
                'people.staff:id,full_name',
                'followUps.responsibleUser:id,name',
                'derivations.destinationDepartment:id,name',
                'derivations.destinationStaff:id,full_name',
                'derivations.destinationUser:id,name',
                'derivations.externalInstitution:id,name',
                'measures.responsibleUser:id,name',
                'interviews.responsibleUser:id,name',
                'protocolActivations.protocol:id,name',
                'protocolActivations.currentStep:id,stage_name',
                'attachments.uploadedBy:id,name',
                'statusLogs.changedBy:id,name',
            ]),
            'student_context' => $case->student
                ? $this->studentContextService->studentSummary($case->student, $case->opened_at, request()->user())
                : null,
        ]);
    }

    public function update(SaveConvivenciaCaseRequest $request, ConvivenciaCase $case): JsonResponse
    {
        $this->authorize('update', $case);

        $updated = $this->caseService->update($case, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Caso actualizado correctamente.',
            'data' => $updated,
            'student_context' => $updated->student
                ? $this->studentContextService->studentSummary($updated->student, $updated->opened_at, $request->user())
                : null,
        ]);
    }

    public function destroy(ConvivenciaCase $case): JsonResponse
    {
        $this->authorize('delete', $case);

        $case->delete();

        return response()->json([
            'message' => 'Caso archivado correctamente.',
        ]);
    }

    public function close(Request $request, ConvivenciaCase $case): JsonResponse
    {
        abort_unless(app(\App\Services\Convivencia\ConvivenciaAccessService::class)->canCloseCases($request->user()), 403);
        $this->authorize('update', $case);

        $payload = $request->validate([
            'resolution' => ['required_without:conclusion', 'nullable', 'string'],
            'conclusion' => ['required_without:resolution', 'nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Caso cerrado correctamente.',
            'data' => $this->caseService->close($case, $payload, $request->user()),
        ]);
    }

    public function storeFollowUp(Request $request, ConvivenciaCase $case): JsonResponse
    {
        $this->authorize('update', $case);

        $payload = $request->validate([
            'follow_up_at' => ['required', 'date'],
            'entry_type' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:191'],
            'notes' => ['required', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        $followUp = $case->followUps()->create([
            'responsible_user_id' => $request->user()->id,
            'follow_up_at' => $payload['follow_up_at'],
            'entry_type' => $payload['entry_type'] ?? 'seguimiento',
            'status' => $payload['status'] ?? 'registrado',
            'title' => $payload['title'] ?? null,
            'notes' => $payload['notes'],
            'next_follow_up_at' => $payload['next_follow_up_at'] ?? null,
        ]);

        if (!empty($payload['next_follow_up_at'])) {
            $case->forceFill([
                'follow_up_due_at' => $payload['next_follow_up_at'],
                'updated_by' => $request->user()->id,
            ])->save();
        }

        return response()->json([
            'message' => 'Seguimiento registrado correctamente.',
            'data' => $followUp->load('responsibleUser:id,name'),
        ], 201);
    }
}
