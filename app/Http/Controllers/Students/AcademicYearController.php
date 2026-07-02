<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreAcademicYearRequest;
use App\Http\Requests\Students\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AcademicYear::query()
                ->withCount(['courseSections', 'enrollments'])
                ->ordered()
                ->get(),
        ]);
    }

    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $academicYear = DB::transaction(function () use ($request) {
            $payload = $request->validated();
            $payload['created_by'] = $request->user()?->id;
            $payload['updated_by'] = $request->user()?->id;

            if (!empty($payload['is_active'])) {
                AcademicYear::query()->update(['is_active' => false]);
            }

            return AcademicYear::query()->create($payload);
        });

        return response()->json([
            'message' => 'Año académico creado correctamente.',
            'data' => $academicYear,
        ], 201);
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $updated = DB::transaction(function () use ($request, $academicYear) {
            $payload = $request->validated();
            $payload['updated_by'] = $request->user()?->id;

            if (!empty($payload['is_active'])) {
                AcademicYear::query()->where('id', '!=', $academicYear->id)->update(['is_active' => false]);
            }

            $academicYear->update($payload);

            return $academicYear->fresh();
        });

        return response()->json([
            'message' => 'Año académico actualizado correctamente.',
            'data' => $updated,
        ]);
    }

    public function setActive(AcademicYear $academicYear): JsonResponse
    {
        DB::transaction(function () use ($academicYear) {
            AcademicYear::query()->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        return response()->json([
            'message' => 'Año académico activo actualizado correctamente.',
            'data' => $academicYear->fresh(),
        ]);
    }
}
