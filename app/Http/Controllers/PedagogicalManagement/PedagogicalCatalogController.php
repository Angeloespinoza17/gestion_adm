<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Models\CourseSection;
use App\Models\LibroDigital\School;
use App\Models\Schedule\ScheduleSubject;
use App\Services\PedagogicalManagement\PedagogicalInstrumentAnalysisService;
use App\Services\PedagogicalManagement\PedagogicalAiReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PedagogicalCatalogController extends Controller
{
    public function __invoke(
        Request $request,
        PedagogicalInstrumentAnalysisService $analysis,
        PedagogicalAiReportService $aiReports,
    ): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('pedagogical-instruments.view'), 403);
        $request->validate([
            'scope' => ['sometimes', Rule::in(['mine'])],
        ]);
        $user = $request->user();
        $mineOnly = $request->string('scope')->toString() === 'mine';
        $defaultSchoolRbd = trim((string) config('libro_digital.default_school.rbd'));
        $schools = School::query()->where('active', true)
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->where(function (Builder $visibility) use ($defaultSchoolRbd, $user): void {
                if ($defaultSchoolRbd !== '') {
                    $visibility->where('rbd', $defaultSchoolRbd);
                }
                $method = $defaultSchoolRbd !== '' ? 'orWhereHas' : 'whereHas';
                $visibility->{$method}('users', fn (Builder $membership) => $membership
                    ->where('users.id', $user->id)->where('lcd_school_users.active', true));
            }))
            ->orderBy('name')->limit(50)->get(['id', 'public_id', 'name', 'rbd']);

        $schoolId = $request->integer('school_id');
        $school = $schoolId
            ? $schools->firstWhere('id', $schoolId)
            : ($schools->firstWhere('rbd', (string) config('libro_digital.default_school.rbd')) ?: $schools->first());
        abort_if($schoolId && ! $school, 403, 'No tienes acceso al establecimiento solicitado.');
        $years = $school ? $school->academicYears()->orderByDesc('academic_years.year')->get([
            'academic_years.id', 'academic_years.name', 'academic_years.year', 'academic_years.is_active', 'academic_years.is_closed',
        ])->unique('id')->values() : collect();
        $yearId = $request->integer('academic_year_id')
            ?: (int) ($years->firstWhere('is_active', true)?->id ?: $years->first()?->id);
        $subjects = Cache::remember('pedagogical-management:active-subjects:v2', 300, fn () => ScheduleSubject::query()
            ->where('active', true)->with('catalogProfile:id,schedule_subject_id,display_name')
            ->orderBy('name')->limit(250)->get(['id', 'name', 'code', 'color'])
            ->map(fn (ScheduleSubject $subject): array => ['id' => $subject->id, 'name' => $subject->resolvedDisplayName(), 'code' => $subject->code, 'color' => $subject->color]));
        $courses = $school && $yearId ? CourseSection::query()->where('academic_year_id', $yearId)->where('active', true)
            ->with('educationLevel:id,name,type')->orderBy('display_name')->limit(100)->get(['id', 'academic_year_id', 'education_level_id', 'display_name'])
            ->map(fn (CourseSection $course): array => [
                'id' => $course->id,
                'name' => $course->display_name,
                'education_level_id' => $course->education_level_id,
                'level' => $course->educationLevel?->name,
            ]) : collect();
        $owners = match (true) {
            ! $school => collect(),
            $mineOnly || ! $user->hasPermission('pedagogical-instruments.view-all') => collect([['id' => $user->id, 'name' => $user->name]]),
            default => $school->users()
                ->where('users.active', true)
                ->where('lcd_school_users.active', true)
                ->where(function (Builder $teachers): void {
                    $teachers->whereRaw("LOWER(COALESCE(lcd_school_users.role_snapshot, '')) = ?", ['docente'])
                        ->orWhereHas('roles', fn (Builder $roles) => $roles->where('roles.slug', 'docente')->where('roles.active', true));
                })
                ->orderBy('users.name')->limit(250)->get(['users.id', 'users.name'])->unique('id')->values()
                ->map(fn ($owner): array => ['id' => $owner->id, 'name' => $owner->name]),
        };

        return response()->json(['data' => [
            'schools' => $schools,
            'selected_school_id' => $school?->id,
            'academic_year' => $years->firstWhere('id', $yearId),
            'subjects' => $subjects,
            'courses' => $courses,
            'owners' => $owners,
            'max_file_kb' => (int) config('pedagogical_management.storage.max_file_kb', 20480),
            'analysis_configured' => $analysis->isConfigured(),
            'analysis_engine' => 'Reglas determinísticas Laravel · smalot/pdfparser',
            'openai_configured' => $aiReports->isConfigured(),
        ]]);
    }
}
