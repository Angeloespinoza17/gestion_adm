<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Contracts\PedagogicalManagement\ClassPresentationContentGenerator;
use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\ClassPresentationTitleSuggestionsRequest;
use App\Models\CourseSection;
use App\Models\LibroDigital\CurriculumUnit;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Models\Schedule\ScheduleSubject;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaApiClient;
use App\Services\PedagogicalManagement\ClassPresentations\ClassPresentationCatalogService;
use App\Services\PedagogicalManagement\ClassPresentations\PresentationTitleSuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassPresentationCatalogController extends Controller
{
    public function options(
        Request $request,
        ClassPresentationCatalogService $catalogs,
        ClassPresentationContentGenerator $generator,
        CanvaApiClient $canva,
    ): JsonResponse {
        $this->authorize('viewAny', ClassPresentation::class);
        $request->validate(['school_id' => ['sometimes', 'integer'], 'academic_year_id' => ['sometimes', 'integer']]);
        $schools = $catalogs->schools($request->user());
        $school = $request->integer('school_id') ? $catalogs->school($request->user(), $request->integer('school_id')) : $schools->first();
        $years = $school ? $catalogs->academicYears($school) : collect();
        $yearId = $request->integer('academic_year_id') ?: (int) ($years->firstWhere('is_active', true)?->id ?: $years->first()?->id);
        $courses = $school && $yearId ? $catalogs->courses($school, $yearId) : collect();
        $authors = ! $school ? collect() : ($request->user()->hasPermission('class-presentations.view-all')
            ? $school->users()->where('users.active', true)->where('lcd_school_users.active', true)->orderBy('users.name')->limit(250)->get(['users.id', 'users.name'])->unique('id')->values()
            : collect([['id' => $request->user()->id, 'name' => $request->user()->name]]));

        return response()->json(['data' => [
            'schools' => $schools, 'selected_school_id' => $school?->id,
            'academic_years' => $years, 'selected_academic_year_id' => $yearId,
            'courses' => $courses, 'authors' => $authors,
            'options' => config('class_presentations.options'),
            'multiple_options' => config('class_presentations.multiple_options'),
            'option_descriptions' => config('class_presentations.option_descriptions'),
            'style_profiles' => collect((array) config('class_presentations.style_profiles'))->map(fn (array $profile): array => [
                'description' => $profile['description'] ?? '',
                'traits' => array_values((array) ($profile['traits'] ?? [])),
            ]),
            'statuses' => collect(ClassPresentationStatus::cases())->map(fn (ClassPresentationStatus $status): array => ['value' => $status->value, 'label' => $this->statusLabel($status)])->values(),
            'openai_configured' => $generator->isConfigured(),
            'canva_configured' => $canva->isConfigured(),
            'max_reference_file_kb' => (int) config('class_presentations.storage.max_reference_file_kb'),
            'max_reference_files' => (int) config('class_presentations.storage.max_reference_files'),
            'poll_interval_ms' => (int) config('class_presentations.generation.poll_interval_ms', 5000),
        ]]);
    }

    public function subjects(Request $request, CourseSection $course, ClassPresentationCatalogService $catalogs): JsonResponse
    {
        $this->validateScope($request, $course, $catalogs);

        return response()->json(['data' => $catalogs->subjects($course)]);
    }

    public function units(Request $request, ScheduleSubject $subject, ClassPresentationCatalogService $catalogs): JsonResponse
    {
        $data = $request->validate(['school_id' => ['required', 'integer'], 'academic_year_id' => ['required', 'integer'], 'course_id' => ['required', 'integer']]);
        $school = $catalogs->school($request->user(), (int) $data['school_id']);
        abort_unless($catalogs->academicYears($school)->contains('id', (int) $data['academic_year_id']), 422, 'El año académico no pertenece al establecimiento.');
        $course = $catalogs->course((int) $data['course_id'], (int) $data['academic_year_id']);
        $subject = $catalogs->subjectForCourse($course, $subject->id);

        return response()->json(['data' => $catalogs->units($course, $subject)]);
    }

    public function objectives(Request $request, CurriculumUnit $unit, ClassPresentationCatalogService $catalogs): JsonResponse
    {
        $data = $request->validate(['school_id' => ['required', 'integer'], 'academic_year_id' => ['required', 'integer'], 'course_id' => ['required', 'integer'], 'subject_id' => ['required', 'integer']]);
        $school = $catalogs->school($request->user(), (int) $data['school_id']);
        abort_unless($catalogs->academicYears($school)->contains('id', (int) $data['academic_year_id']), 422, 'El año académico no pertenece al establecimiento.');
        $course = $catalogs->course((int) $data['course_id'], (int) $data['academic_year_id']);
        $subject = $catalogs->subjectForCourse($course, (int) $data['subject_id']);
        $unit = $catalogs->unitForCourseSubject($course, $subject, $unit->id);

        return response()->json(['data' => $catalogs->objectives($unit)]);
    }

    public function titles(ClassPresentationTitleSuggestionsRequest $request, ClassPresentationCatalogService $catalogs, PresentationTitleSuggester $titles): JsonResponse
    {
        $data = $request->validated();
        $school = $catalogs->school($request->user(), (int) $data['school_id']);
        abort_unless($catalogs->academicYears($school)->contains('id', (int) $data['academic_year_id']), 422, 'El año académico no pertenece al establecimiento.');
        $course = $catalogs->course((int) $data['course_id'], (int) $data['academic_year_id']);
        $subject = $catalogs->subjectForCourse($course, (int) $data['subject_id']);
        $unit = $catalogs->unitForCourseSubject($course, $subject, (int) $data['unit_id']);
        $objectives = $catalogs->selectedObjectives($unit, array_map('intval', $data['learning_objective_ids']));

        return response()->json(['data' => $titles->suggest(
            $unit->official_title ?: $unit->friendly_focus ?: $unit->unit_code,
            $objectives->map->only(['code', 'description'])->all(), (string) $data['class_type'],
        )]);
    }

    private function validateScope(Request $request, CourseSection $course, ClassPresentationCatalogService $catalogs): void
    {
        $data = $request->validate(['school_id' => ['required', 'integer'], 'academic_year_id' => ['required', 'integer']]);
        $school = $catalogs->school($request->user(), (int) $data['school_id']);
        abort_unless($catalogs->academicYears($school)->contains('id', (int) $data['academic_year_id']), 422, 'El año académico no pertenece al establecimiento.');
        abort_unless((int) $course->academic_year_id === (int) $data['academic_year_id'] && $course->active, 422, 'El curso no pertenece al año seleccionado.');
    }

    private function statusLabel(ClassPresentationStatus $status): string
    {
        return match ($status) {
            ClassPresentationStatus::Draft => 'Borrador', ClassPresentationStatus::Queued => 'En cola',
            ClassPresentationStatus::PreparingContent => 'Preparando contenido',
            ClassPresentationStatus::GeneratingPresentation => 'Generando presentación',
            ClassPresentationStatus::Validating => 'Validando', ClassPresentationStatus::Ready => 'Lista',
            ClassPresentationStatus::Failed => 'Fallida', ClassPresentationStatus::Archived => 'Archivada',
        };
    }
}
