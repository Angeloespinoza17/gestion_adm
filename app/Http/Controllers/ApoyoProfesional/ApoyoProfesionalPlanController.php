<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApoyoProfesional\SaveApoyoPlanRequest;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Services\ApoyoProfesional\ApoyoProfesionalPlanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApoyoProfesionalPlanController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalPlanService $planService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApoyoPlan::class);

        $query = ApoyoPlan::query()
            ->with([
                'student:id,first_name,last_name,registered_name,rut',
                'responsibleProfessional.staff:id,full_name',
                'responsibleUser:id,name',
                'actions',
            ]);

        if (!$request->user()->isSuperAdmin() && !$request->user()->hasPermission('ver_atenciones_confidenciales_apoyo_profesional') && !$request->user()->hasPermission('ver_atenciones_equipo_apoyo_profesional')) {
            $query->where('responsible_user_id', $request->user()->id);
        }

        $status = trim((string) $request->query('status'));
        $studentId = $request->query('student_profile_id');
        $area = trim((string) $request->query('area_name'));

        $query
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($studentId, fn (Builder $builder) => $builder->where('student_profile_id', $studentId))
            ->when($area !== '', fn (Builder $builder) => $builder->where('area_name', $area));

        return response()->json(
            $query->latest('start_date')->paginate((int) $request->query('per_page', 12))
        );
    }

    public function store(SaveApoyoPlanRequest $request): JsonResponse
    {
        $this->authorize('create', ApoyoPlan::class);

        return response()->json([
            'message' => 'Plan de apoyo registrado correctamente.',
            'data' => $this->planService->store($request->validated(), $request->user()),
        ], 201);
    }

    public function show(ApoyoPlan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return response()->json([
            'data' => $plan->load([
                'student:id,first_name,last_name,registered_name,rut',
                'responsibleProfessional.staff:id,full_name',
                'responsibleUser:id,name',
                'actions',
                'documents.uploadedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveApoyoPlanRequest $request, ApoyoPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        return response()->json([
            'message' => 'Plan de apoyo actualizado correctamente.',
            'data' => $this->planService->update($plan, $request->validated(), $request->user()),
        ]);
    }
}
