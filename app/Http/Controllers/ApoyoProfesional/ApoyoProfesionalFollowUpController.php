<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApoyoProfesional\SaveApoyoSeguimientoRequest;
use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApoyoProfesionalFollowUpController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApoyoSeguimiento::class);

        $attentionIds = $this->accessService->applyAttentionVisibility(
            \App\Models\ApoyoProfesional\ApoyoAtencion::query(),
            $request->user(),
        )->pluck('id');

        $query = ApoyoSeguimiento::query()
            ->with([
                'attention:id,student_profile_id,reason_summary,status,confidentiality_level',
                'student:id,first_name,last_name,registered_name,rut',
                'responsibleProfessional.staff:id,full_name',
                'responsibleUser:id,name',
            ])
            ->when($attentionIds->isNotEmpty(), fn ($builder) => $builder->whereIn('attention_id', $attentionIds), fn ($builder) => $builder->whereRaw('1 = 0'));

        $status = trim((string) $request->query('status'));
        $studentId = $request->query('student_profile_id');
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        $query
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($studentId, fn ($builder) => $builder->where('student_profile_id', $studentId))
            ->when($from !== '', fn ($builder) => $builder->whereDate('scheduled_at', '>=', $from))
            ->when($to !== '', fn ($builder) => $builder->whereDate('scheduled_at', '<=', $to));

        return response()->json(
            $query->latest('scheduled_at')->paginate((int) $request->query('per_page', 12))
        );
    }

    public function store(SaveApoyoSeguimientoRequest $request): JsonResponse
    {
        $this->authorize('create', ApoyoSeguimiento::class);

        $followUp = ApoyoSeguimiento::query()->create(array_merge(
            $request->validated(),
            [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        ));

        return response()->json([
            'message' => 'Seguimiento registrado correctamente.',
            'data' => $followUp->load(['responsibleProfessional.staff:id,full_name', 'responsibleUser:id,name']),
        ], 201);
    }

    public function show(ApoyoSeguimiento $followUp): JsonResponse
    {
        $this->authorize('view', $followUp);

        return response()->json([
            'data' => $followUp->load([
                'attention:id,student_profile_id,reason_summary,status,confidentiality_level',
                'student:id,first_name,last_name,registered_name,rut',
                'responsibleProfessional.staff:id,full_name',
                'responsibleUser:id,name',
                'documents.uploadedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveApoyoSeguimientoRequest $request, ApoyoSeguimiento $followUp): JsonResponse
    {
        $this->authorize('update', $followUp);

        $followUp->fill(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()->id],
        ))->save();

        return response()->json([
            'message' => 'Seguimiento actualizado correctamente.',
            'data' => $followUp->fresh(['responsibleProfessional.staff:id,full_name', 'responsibleUser:id,name']),
        ]);
    }

    public function destroy(ApoyoSeguimiento $followUp): JsonResponse
    {
        $this->authorize('update', $followUp);
        $followUp->delete();

        return response()->json([
            'message' => 'Seguimiento eliminado correctamente.',
        ]);
    }
}
