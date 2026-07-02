<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaMeasureRequest;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Services\Convivencia\ConvivenciaMeasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaMeasureController extends Controller
{
    public function __construct(
        private readonly ConvivenciaMeasureService $measureService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaMeasure::class);

        $query = app(\App\Services\Convivencia\ConvivenciaAccessService::class)
            ->applyMeasureVisibility(
                ConvivenciaMeasure::query()->with([
                    'case:id,folio,status',
                    'student:id,first_name,last_name,registered_name,rut',
                    'courseSection:id,display_name',
                    'type:id,name',
                    'responsibleUser:id,name',
                    'responsibleStaff:id,full_name',
                ]),
                $request->user(),
            );

        $query
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('case_id'), fn ($builder, $value) => $builder->where('case_id', $value))
            ->when($request->query('student_profile_id'), fn ($builder, $value) => $builder->where('student_profile_id', $value))
            ->when($request->query('course_section_id'), fn ($builder, $value) => $builder->where('course_section_id', $value))
            ->when($request->query('from'), fn ($builder, $value) => $builder->whereDate('assigned_at', '>=', $value))
            ->when($request->query('to'), fn ($builder, $value) => $builder->whereDate('assigned_at', '<=', $value));

        return response()->json($query->latest('assigned_at')->paginate((int) $request->query('per_page', 12)));
    }

    public function store(SaveConvivenciaMeasureRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaMeasure::class);

        $measure = $this->measureService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Medida formativa registrada correctamente.',
            'data' => $measure,
        ], 201);
    }

    public function show(ConvivenciaMeasure $measure): JsonResponse
    {
        $this->authorize('view', $measure);

        return response()->json([
            'data' => $measure->load([
                'case:id,folio,status,classification_label,criticality_label',
                'student:id,first_name,last_name,registered_name,rut',
                'courseSection:id,display_name',
                'type:id,name',
                'responsibleUser:id,name',
                'responsibleStaff:id,full_name',
                'validator:id,name',
                'attachments.uploadedBy:id,name',
                'statusLogs.changedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveConvivenciaMeasureRequest $request, ConvivenciaMeasure $measure): JsonResponse
    {
        $this->authorize('update', $measure);

        $updated = $this->measureService->update($measure, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Medida formativa actualizada correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(ConvivenciaMeasure $measure): JsonResponse
    {
        $this->authorize('delete', $measure);

        $measure->delete();

        return response()->json([
            'message' => 'Medida formativa archivada correctamente.',
        ]);
    }
}
