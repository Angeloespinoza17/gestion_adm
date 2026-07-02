<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApoyoProfesional\SaveApoyoEntrevistaRequest;
use App\Models\ApoyoProfesional\ApoyoEntrevista;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApoyoProfesionalInterviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApoyoEntrevista::class);

        $query = ApoyoEntrevista::query()
            ->with([
                'student:id,first_name,last_name,registered_name,rut',
                'professional.staff:id,full_name',
                'professionalUser:id,name',
            ]);

        if (!$request->user()->isSuperAdmin() && !$request->user()->hasPermission('ver_atenciones_confidenciales_apoyo_profesional') && !$request->user()->hasPermission('ver_atenciones_equipo_apoyo_profesional')) {
            $query->where('professional_user_id', $request->user()->id);
        }

        $status = trim((string) $request->query('status'));
        $studentId = $request->query('student_profile_id');
        $type = trim((string) $request->query('interview_type'));

        $query
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($studentId, fn (Builder $builder) => $builder->where('student_profile_id', $studentId))
            ->when($type !== '', fn (Builder $builder) => $builder->where('interview_type', $type));

        return response()->json(
            $query->latest('interview_at')->paginate((int) $request->query('per_page', 12))
        );
    }

    public function store(SaveApoyoEntrevistaRequest $request): JsonResponse
    {
        $this->authorize('create', ApoyoEntrevista::class);

        $interview = ApoyoEntrevista::query()->create(array_merge(
            $request->validated(),
            [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        ));

        return response()->json([
            'message' => 'Entrevista registrada correctamente.',
            'data' => $interview->load(['professional.staff:id,full_name', 'professionalUser:id,name']),
        ], 201);
    }

    public function show(ApoyoEntrevista $interview): JsonResponse
    {
        $this->authorize('view', $interview);

        return response()->json([
            'data' => $interview->load([
                'student:id,first_name,last_name,registered_name,rut',
                'professional.staff:id,full_name',
                'professionalUser:id,name',
                'documents.uploadedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveApoyoEntrevistaRequest $request, ApoyoEntrevista $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        $interview->fill(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()->id],
        ))->save();

        return response()->json([
            'message' => 'Entrevista actualizada correctamente.',
            'data' => $interview->fresh(['professional.staff:id,full_name', 'professionalUser:id,name']),
        ]);
    }

    public function destroy(ApoyoEntrevista $interview): JsonResponse
    {
        $this->authorize('update', $interview);
        $interview->delete();

        return response()->json([
            'message' => 'Entrevista eliminada correctamente.',
        ]);
    }
}
