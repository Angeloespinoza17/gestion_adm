<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Schedule\SchoolDayTemplate;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;

class ScheduleCatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $academicYears = AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active', 'is_closed']);
        $activeAcademicYearId = $academicYears->firstWhere('is_active', true)?->id ?: $academicYears->first()?->id;

        $courses = CourseSection::query()
            ->with([
                'academicYear:id,name,year,is_active',
                'educationLevel:id,name,order,type,default_school_day_template_id',
                'schoolDayTemplate:id,name,start_time,end_time,active',
            ])
            ->when($activeAcademicYearId, fn ($query) => $query->where('academic_year_id', $activeAcademicYearId))
            ->orderBy('education_level_id')
            ->orderBy('section_name')
            ->get();

        return response()->json([
            'academic_years' => $academicYears,
            'active_academic_year_id' => $activeAcademicYearId,
            'jornadas' => SchoolDayTemplate::query()
                ->withCount('blocks')
                ->when($activeAcademicYearId, fn ($query) => $query->where('academic_year_id', $activeAcademicYearId))
                ->orderBy('name')
                ->get(),
            'education_levels' => EducationLevel::query()
                ->with('defaultSchoolDayTemplate:id,name,start_time,end_time,active')
                ->orderBy('order')
                ->get(['id', 'name', 'order', 'type', 'default_school_day_template_id']),
            'courses' => $courses,
            'subjects' => ScheduleSubject::query()->orderBy('name')->get(),
            'teachers' => Staff::query()
                ->with('cargo:id,name,slug')
                ->where('active', true)
                ->where(function ($query) {
                    $query
                        ->whereHas('cargo', fn ($cargoQuery) => $cargoQuery->whereIn('slug', ['docente', 'coordinador_academico']))
                        ->orWhereHas('departments', fn ($departmentQuery) => $departmentQuery->whereIn('slug', ['docentes', 'coordinacion-academica', 'pie']));
                })
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'institutional_email', 'personal_email', 'cargo_id', 'contract_hours']),
            'layer_types' => [
                ['value' => 'lective', 'label' => 'Clases lectivas'],
                ['value' => 'non_lective', 'label' => 'Horas no lectivas'],
                ['value' => 'extracurricular', 'label' => 'Extraprogramaticas'],
                ['value' => 'coordination', 'label' => 'Coordinacion'],
                ['value' => 'meeting', 'label' => 'Reuniones'],
                ['value' => 'pie', 'label' => 'PIE o apoyo'],
                ['value' => 'replacement', 'label' => 'Reemplazos'],
                ['value' => 'workshop', 'label' => 'Talleres'],
                ['value' => 'availability_block', 'label' => 'Bloqueos o disponibilidad'],
                ['value' => 'other', 'label' => 'Otro'],
            ],
            'activity_types' => [
                ['value' => 'lective_class', 'label' => 'Clase lectiva'],
                ['value' => 'non_lective', 'label' => 'Hora no lectiva'],
                ['value' => 'meeting', 'label' => 'Reunion'],
                ['value' => 'coordination', 'label' => 'Coordinacion'],
                ['value' => 'extracurricular', 'label' => 'Extraprogramatica'],
                ['value' => 'pie', 'label' => 'PIE o apoyo'],
                ['value' => 'replacement', 'label' => 'Reemplazo'],
                ['value' => 'workshop', 'label' => 'Taller'],
                ['value' => 'availability_block', 'label' => 'Bloqueo'],
            ],
            'days_of_week' => [
                ['value' => 1, 'label' => 'Lunes'],
                ['value' => 2, 'label' => 'Martes'],
                ['value' => 3, 'label' => 'Miercoles'],
                ['value' => 4, 'label' => 'Jueves'],
                ['value' => 5, 'label' => 'Viernes'],
                ['value' => 6, 'label' => 'Sabado'],
                ['value' => 7, 'label' => 'Domingo'],
            ],
        ]);
    }
}
