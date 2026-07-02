<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaInterviewRequest;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Services\Convivencia\ConvivenciaInterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaInterviewController extends Controller
{
    public function __construct(
        private readonly ConvivenciaInterviewService $interviewService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaInterview::class);

        $query = app(\App\Services\Convivencia\ConvivenciaAccessService::class)
            ->applyInterviewVisibility(
                ConvivenciaInterview::query()->with([
                    'case:id,folio,status',
                    'student:id,first_name,last_name,registered_name,rut',
                    'courseSection:id,display_name',
                    'responsibleUser:id,name',
                    'responsibleStaff:id,full_name',
                ])->withCount('participants'),
                $request->user(),
            );

        $query
            ->when($request->query('follow_up_status'), fn ($builder, $value) => $builder->where('follow_up_status', $value))
            ->when($request->query('case_id'), fn ($builder, $value) => $builder->where('case_id', $value))
            ->when($request->query('student_profile_id'), fn ($builder, $value) => $builder->where('student_profile_id', $value))
            ->when($request->query('course_section_id'), fn ($builder, $value) => $builder->where('course_section_id', $value))
            ->when($request->query('from'), fn ($builder, $value) => $builder->whereDate('interview_at', '>=', $value))
            ->when($request->query('to'), fn ($builder, $value) => $builder->whereDate('interview_at', '<=', $value));

        return response()->json($query->latest('interview_at')->paginate((int) $request->query('per_page', 12)));
    }

    public function store(SaveConvivenciaInterviewRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaInterview::class);

        $interview = $this->interviewService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Entrevista registrada correctamente.',
            'data' => $interview,
        ], 201);
    }

    public function show(ConvivenciaInterview $interview): JsonResponse
    {
        $this->authorize('view', $interview);

        return response()->json([
            'data' => $interview->load([
                'case:id,folio,status',
                'student:id,first_name,last_name,registered_name,rut',
                'courseSection:id,display_name',
                'type:id,name',
                'responsibleUser:id,name',
                'responsibleStaff:id,full_name',
                'participants.student:id,first_name,last_name,registered_name,rut',
                'participants.user:id,name',
                'participants.staff:id,full_name',
                'attachments.uploadedBy:id,name',
                'statusLogs.changedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveConvivenciaInterviewRequest $request, ConvivenciaInterview $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        $updated = $this->interviewService->update($interview, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Entrevista actualizada correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(ConvivenciaInterview $interview): JsonResponse
    {
        $this->authorize('delete', $interview);

        $interview->delete();

        return response()->json([
            'message' => 'Entrevista archivada correctamente.',
        ]);
    }
}
