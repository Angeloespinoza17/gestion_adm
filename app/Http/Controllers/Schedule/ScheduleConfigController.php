<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Schedule\SchoolScheduleConfig;
use App\Services\Schedule\ScheduleConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleConfigController extends Controller
{
    public function __construct(private readonly ScheduleConfigService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service
                ->getForAcademicYear($request->integer('academic_year_id') ?: null)
                ->load('academicYear:id,name,year,is_active,is_closed'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'pedagogical_hour_minutes' => ['required', 'integer', 'min:20', 'max:120'],
            'default_lective_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_non_lective_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'calculation_base' => ['required', Rule::in(SchoolScheduleConfig::CALCULATION_BASES)],
            'rounding_mode' => ['required', Rule::in(SchoolScheduleConfig::ROUNDING_MODES)],
            'strict_validation_enabled' => ['required', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Configuracion de horario actualizada correctamente.',
            'data' => $this->service->update($payload),
        ]);
    }
}
