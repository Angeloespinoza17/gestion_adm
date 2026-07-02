<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaIdpsResultRequest;
use App\Models\Convivencia\ConvivenciaIdpsDimension;
use App\Models\Convivencia\ConvivenciaIdpsInstrument;
use App\Models\Convivencia\ConvivenciaIdpsPeriod;
use App\Models\Convivencia\ConvivenciaIdpsResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaIdpsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        abort_unless($this->canView($request), 403);

        $results = ConvivenciaIdpsResult::query()
            ->with([
                'period:id,name,status',
                'dimension:id,code,name',
                'instrument:id,name',
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'educationLevel:id,name',
                'relatedPlan:id,name',
                'createdBy:id,name',
            ])
            ->when($request->query('period_id'), fn ($builder, $value) => $builder->where('period_id', $value))
            ->when($request->query('dimension_id'), fn ($builder, $value) => $builder->where('dimension_id', $value))
            ->when($request->query('academic_year_id'), fn ($builder, $value) => $builder->where('academic_year_id', $value))
            ->when($request->query('course_section_id'), fn ($builder, $value) => $builder->where('course_section_id', $value))
            ->when($request->query('education_level_id'), fn ($builder, $value) => $builder->where('education_level_id', $value))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 12));

        return response()->json([
            'periods' => ConvivenciaIdpsPeriod::query()
                ->with('academicYear:id,name,year')
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->get(),
            'dimensions' => ConvivenciaIdpsDimension::query()
                ->with('instruments')
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'results' => $results,
        ]);
    }

    public function storePeriod(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $payload = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:160'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $period = ConvivenciaIdpsPeriod::query()->create(array_merge($payload, [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Período IDPS registrado correctamente.',
            'data' => $period->load('academicYear:id,name,year'),
        ], 201);
    }

    public function updatePeriod(Request $request, ConvivenciaIdpsPeriod $period): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $payload = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'name' => ['sometimes', 'string', 'max:160'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $period->fill(array_merge($payload, [
            'updated_by' => $request->user()->id,
        ]))->save();

        return response()->json([
            'message' => 'Período IDPS actualizado correctamente.',
            'data' => $period->fresh('academicYear:id,name,year'),
        ]);
    }

    public function storeDimension(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $payload = $request->validate([
            'code' => ['required', 'string', 'max:80', 'unique:convivencia_idps_dimensions,code'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $dimension = ConvivenciaIdpsDimension::query()->create(array_merge($payload, [
            'active' => $payload['active'] ?? true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Dimensión IDPS registrada correctamente.',
            'data' => $dimension,
        ], 201);
    }

    public function updateDimension(Request $request, ConvivenciaIdpsDimension $dimension): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $payload = $request->validate([
            'code' => ['sometimes', 'string', 'max:80', 'unique:convivencia_idps_dimensions,code,' . $dimension->id],
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $dimension->fill(array_merge($payload, [
            'updated_by' => $request->user()->id,
        ]))->save();

        return response()->json([
            'message' => 'Dimensión IDPS actualizada correctamente.',
            'data' => $dimension->fresh(),
        ]);
    }

    public function storeInstrument(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $payload = $request->validate([
            'dimension_id' => ['nullable', 'integer', 'exists:convivencia_idps_dimensions,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'response_type' => ['required', 'string', 'max:80'],
            'scale_label' => ['nullable', 'string', 'max:160'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $instrument = ConvivenciaIdpsInstrument::query()->create(array_merge($payload, [
            'active' => $payload['active'] ?? true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Instrumento IDPS registrado correctamente.',
            'data' => $instrument->load('dimension:id,code,name'),
        ], 201);
    }

    public function updateInstrument(Request $request, ConvivenciaIdpsInstrument $instrument): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $payload = $request->validate([
            'dimension_id' => ['nullable', 'integer', 'exists:convivencia_idps_dimensions,id'],
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'response_type' => ['sometimes', 'string', 'max:80'],
            'scale_label' => ['nullable', 'string', 'max:160'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $instrument->fill(array_merge($payload, [
            'updated_by' => $request->user()->id,
        ]))->save();

        return response()->json([
            'message' => 'Instrumento IDPS actualizado correctamente.',
            'data' => $instrument->fresh('dimension:id,code,name'),
        ]);
    }

    public function storeResult(SaveConvivenciaIdpsResultRequest $request): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $result = new ConvivenciaIdpsResult();
        $this->fillResult($result, $request->validated(), $request->user()->id, true);

        return response()->json([
            'message' => 'Resultado IDPS registrado correctamente.',
            'data' => $this->loadResult($result),
        ], 201);
    }

    public function updateResult(SaveConvivenciaIdpsResultRequest $request, ConvivenciaIdpsResult $result): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $this->fillResult($result, $request->validated(), $request->user()->id, false);

        return response()->json([
            'message' => 'Resultado IDPS actualizado correctamente.',
            'data' => $this->loadResult($result),
        ]);
    }

    private function fillResult(ConvivenciaIdpsResult $result, array $payload, int $userId, bool $creating): void
    {
        $referenceLabel = $payload['reference_label']
            ?? ($payload['course_section_id']
                ? optional(\App\Models\CourseSection::query()->find($payload['course_section_id']))->display_name
                : ($payload['education_level_id']
                    ? optional(\App\Models\EducationLevel::query()->find($payload['education_level_id']))->name
                    : null));

        $result->fill(array_merge($payload, [
            'reference_label' => $referenceLabel,
            'updated_by' => $userId,
        ]));

        if ($creating) {
            $result->created_by = $userId;
        }

        $result->save();
    }

    private function loadResult(ConvivenciaIdpsResult $result): ConvivenciaIdpsResult
    {
        return $result->fresh([
            'period:id,name,status',
            'dimension:id,code,name',
            'instrument:id,name',
            'academicYear:id,name,year',
            'courseSection:id,display_name',
            'educationLevel:id,name',
            'relatedPlan:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);
    }

    private function canView(Request $request): bool
    {
        $access = app(\App\Services\Convivencia\ConvivenciaAccessService::class);

        return $access->canViewCourseReports($request->user())
            || $access->canManagePlans($request->user())
            || $access->canManageSettings($request->user())
            || $access->canViewDashboard($request->user());
    }

    private function canManage(Request $request): bool
    {
        $access = app(\App\Services\Convivencia\ConvivenciaAccessService::class);

        return $access->canManagePlans($request->user()) || $access->canManageSettings($request->user());
    }
}
