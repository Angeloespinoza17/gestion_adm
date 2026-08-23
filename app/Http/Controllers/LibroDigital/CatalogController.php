<?php

namespace App\Http\Controllers\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Schedule\SchoolDayBlock;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CatalogController extends LibroDigitalController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $schools = School::query()->where('active', true)
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->whereHas('users', fn (Builder $membership) => $membership
                ->where('users.id', $user->id)
                ->where('lcd_school_users.active', true)
                ->where(fn (Builder $dates) => $dates->whereNull('lcd_school_users.valid_from')->orWhere('lcd_school_users.valid_from', '<=', $today))
                ->where(fn (Builder $dates) => $dates->whereNull('lcd_school_users.valid_to')->orWhere('lcd_school_users.valid_to', '>=', $today))))
            ->orderBy('name')->get();

        $requestedSchoolId = $request->integer('school_id') ?: null;
        $activeSchool = $requestedSchoolId
            ? $schools->firstWhere('id', $requestedSchoolId)
            : $schools->first();

        if ($requestedSchoolId && ! $activeSchool) {
            abort(403, 'No tienes acceso al establecimiento solicitado.');
        }

        $yearIds = $activeSchool?->academicYears()->wherePivot('active', true)->pluck('academic_years.id');
        $years = AcademicYear::query()->whereIn('id', $yearIds ?? [])->ordered()->get();
        $activeYearId = $request->integer('academic_year_id')
            ?: optional($years->firstWhere('is_active', true))->id
            ?: optional($years->first())->id;

        $courses = CourseSection::query()->with('educationLevel')
            ->when($activeYearId, fn (Builder $query) => $query->where('academic_year_id', $activeYearId))
            ->where('active', true)->orderBy('display_name')->get();

        $catalogs = [
            'schools' => $schools->map(fn (School $school) => [
                'id' => $school->id,
                'public_id' => $school->public_id,
                'name' => $school->name,
                'rbd' => $school->rbd,
                'timezone' => $school->timezone,
            ])->values(),
            'academic_years' => $years->map->only(['id', 'name', 'year', 'starts_at', 'ends_at', 'is_active', 'is_closed'])->values(),
            'education_levels' => $courses->pluck('educationLevel')->filter()->unique('id')->values()->map->only(['id', 'name', 'type', 'order']),
            'course_sections' => $courses->map(fn (CourseSection $course) => [
                'id' => $course->id,
                'academic_year_id' => $course->academic_year_id,
                'education_level_id' => $course->education_level_id,
                'display_name' => $course->display_name,
                'section_name' => $course->section_name,
                'level' => $course->educationLevel?->only(['id', 'name', 'type']),
            ])->values(),
            'subjects' => ScheduleSubject::query()
                ->with('catalogProfile')
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'area', 'color', 'active'])
                ->map(fn (ScheduleSubject $subject): array => [
                    'id' => $subject->id,
                    'name' => $subject->resolvedDisplayName(),
                    'technical_name' => $subject->name,
                    'code' => $subject->code,
                    'area' => $subject->area,
                    'color' => $subject->color,
                    'active' => (bool) $subject->active,
                    'education_types' => $subject->catalogProfile?->education_types ?? [],
                ])->values(),
            'teachers' => Staff::query()->where('active', true)->orderBy('full_name')->get(['id', 'full_name', 'institutional_email', 'active']),
            'school_day_blocks' => SchoolDayBlock::query()->where('assignable', true)->orderBy('order')->get(['id', 'school_day_template_id', 'day_of_week', 'start_time', 'end_time', 'label', 'order']),
            'regulatory_profiles' => RegulatoryProfile::query()->where('active', true)->orderByDesc('effective_from')->get(['id', 'public_id', 'code', 'name', 'version', 'effective_from', 'effective_to']),
            'attendance_statuses' => [
                ['value' => 'present', 'label' => 'Presente'],
                ['value' => 'absent', 'label' => 'Ausente'],
                ['value' => 'late', 'label' => 'Atraso'],
                ['value' => 'left_early', 'label' => 'Retiro anticipado'],
                ['value' => 'not_applicable', 'label' => 'No aplica'],
            ],
        ];

        return $this->dataResponse([
            'catalogs' => $catalogs,
            'capabilities' => $this->capabilities($request),
            'active_school_id' => $activeSchool?->id,
            'active_academic_year_id' => $activeYearId,
        ]);
    }
}
