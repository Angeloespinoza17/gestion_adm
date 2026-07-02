<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaSociogramRequest;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Services\Convivencia\ConvivenciaSociogramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaSociogramController extends Controller
{
    public function __construct(
        private readonly ConvivenciaSociogramService $sociogramService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaSociogram::class);

        $query = app(\App\Services\Convivencia\ConvivenciaAccessService::class)
            ->applySociogramVisibility(
                ConvivenciaSociogram::query()->with([
                    'academicYear:id,name,year',
                    'courseSection:id,display_name',
                    'createdBy:id,name',
                ])->withCount(['questions', 'answers']),
                $request->user(),
            );

        $query
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('academic_year_id'), fn ($builder, $value) => $builder->where('academic_year_id', $value))
            ->when($request->query('course_section_id'), fn ($builder, $value) => $builder->where('course_section_id', $value))
            ->when($request->query('from'), fn ($builder, $value) => $builder->whereDate('applied_on', '>=', $value))
            ->when($request->query('to'), fn ($builder, $value) => $builder->whereDate('applied_on', '<=', $value));

        return response()->json($query->latest('applied_on')->paginate((int) $request->query('per_page', 12)));
    }

    public function store(SaveConvivenciaSociogramRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaSociogram::class);

        $sociogram = $this->sociogramService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Sociograma registrado correctamente.',
            'data' => $sociogram,
        ], 201);
    }

    public function show(ConvivenciaSociogram $sociogram): JsonResponse
    {
        $this->authorize('view', $sociogram);

        return response()->json([
            'data' => $sociogram->load([
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'questions',
                'answers.respondentStudent:id,first_name,last_name,registered_name,rut',
                'answers.selectedStudent:id,first_name,last_name,registered_name,rut',
                'createdBy:id,name',
                'updatedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveConvivenciaSociogramRequest $request, ConvivenciaSociogram $sociogram): JsonResponse
    {
        $this->authorize('update', $sociogram);

        $updated = $this->sociogramService->update($sociogram, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Sociograma actualizado correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(ConvivenciaSociogram $sociogram): JsonResponse
    {
        $this->authorize('delete', $sociogram);

        $sociogram->delete();

        return response()->json([
            'message' => 'Sociograma archivado correctamente.',
        ]);
    }
}
