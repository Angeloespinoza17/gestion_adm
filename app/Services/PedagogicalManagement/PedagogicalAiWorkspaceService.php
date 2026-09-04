<?php

namespace App\Services\PedagogicalManagement;

use App\Enums\PedagogicalManagement\InstrumentStatus;
use App\Enums\PedagogicalManagement\InstrumentWorkflowStatus;
use App\Exceptions\PedagogicalManagement\PedagogicalInstrumentException;
use App\Models\CourseSection;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentAiReport;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PedagogicalAiWorkspaceService
{
    public function __construct(
        private readonly PedagogicalInstrumentFileService $files,
        private readonly PedagogicalAiReportService $aiReports,
        private readonly AuditEventWriter $audit,
    ) {}

    /** @return array<string,mixed> */
    public function catalogs(School $school): array
    {
        $years = $school->academicYears()
            ->orderByDesc('academic_years.year')
            ->get([
                'academic_years.id',
                'academic_years.name',
                'academic_years.year',
                'academic_years.is_active',
                'academic_years.is_closed',
            ])
            ->unique('id')
            ->values();
        $academicYear = $years->firstWhere('is_active', true) ?: $years->first();
        $subjects = Cache::remember('pedagogical-management:active-subjects:v2', 300, fn () => ScheduleSubject::query()
            ->where('active', true)
            ->with('catalogProfile:id,schedule_subject_id,display_name')
            ->orderBy('name')
            ->limit(250)
            ->get(['id', 'name', 'code', 'color'])
            ->map(fn (ScheduleSubject $subject): array => [
                'id' => $subject->id,
                'name' => $subject->resolvedDisplayName(),
                'code' => $subject->code,
                'color' => $subject->color,
            ]));
        $courses = $academicYear
            ? CourseSection::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('active', true)
                ->with('educationLevel:id,name,type')
                ->orderBy('display_name')
                ->limit(100)
                ->get(['id', 'academic_year_id', 'education_level_id', 'display_name'])
                ->map(fn (CourseSection $course): array => [
                    'id' => $course->id,
                    'name' => $course->display_name,
                    'education_level_id' => $course->education_level_id,
                    'level' => $course->educationLevel?->name,
                ])
            : collect();

        return [
            'school' => ['id' => $school->id, 'name' => $school->name, 'rbd' => $school->rbd],
            'academic_year' => $academicYear,
            'subjects' => $subjects,
            'courses' => $courses,
            'max_file_kb' => (int) config('pedagogical_management.storage.max_file_kb', 30720),
            'openai_configured' => $this->aiReports->isConfigured(),
        ];
    }

    /**
     * @param  array{subject_id:int,course_id:int}  $data
     * @return array{0:PedagogicalInstrument,1:PedagogicalInstrumentAiReport}
     */
    public function create(
        array $data,
        UploadedFile $uploadedFile,
        School $school,
        User $actor,
        Request $request,
    ): array {
        if (! $this->aiReports->isConfigured()) {
            throw new PedagogicalInstrumentException(
                'La revisión con IA no está configurada en este entorno.',
                'OPENAI_NOT_CONFIGURED',
                503,
            );
        }

        $course = CourseSection::query()
            ->whereKey($data['course_id'])
            ->where('active', true)
            ->firstOrFail();
        $this->assertAcademicContext($school, $course, (int) $data['subject_id']);
        $metadata = $this->files->inspect($uploadedFile, (int) $school->id);

        [$instrument, $file] = DB::transaction(function () use (
            $actor,
            $course,
            $data,
            $metadata,
            $school,
            $uploadedFile,
        ): array {
            $instrument = PedagogicalInstrument::query()->create([
                'school_id' => $school->id,
                'academic_year_id' => $course->academic_year_id,
                'owner_user_id' => $actor->id,
                'subject_id' => $data['subject_id'],
                'title' => $this->titleFromUpload($uploadedFile->getClientOriginalName()),
                'grade_label' => $course->display_name,
                'instrument_type' => 'other',
                'evaluation_purpose' => 'other',
                'work_modality' => 'individual',
                'status' => InstrumentStatus::Uploaded,
                'workflow_status' => InstrumentWorkflowStatus::Draft,
                'submitted_at' => null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $instrument->courses()->sync([$course->id]);
            $file = $this->files->store($instrument, $uploadedFile, $metadata, $actor);

            return [$instrument, $file];
        }, 3);

        $this->audit->write(
            'pedagogical.instrument.ai_workspace_created',
            'ai_workspace',
            $instrument,
            actor: $actor,
            schoolId: $school->id,
            academicYearId: $instrument->academic_year_id,
            after: [
                'instrument_uuid' => $instrument->uuid,
                'file_uuid' => $file->uuid,
                'course_id' => $course->id,
                'subject_id' => (int) $data['subject_id'],
            ],
            request: $request,
        );

        $report = $this->aiReports->requestReport($instrument, $file, $actor, $request);

        return [$instrument, $report];
    }

    public function visibleQuery(User $user): Builder
    {
        return PedagogicalInstrument::query()
            ->where('workflow_status', InstrumentWorkflowStatus::Draft->value)
            ->where('owner_user_id', $user->id)
            ->where('created_by', $user->id);
    }

    public function assertCanUse(User $user, PedagogicalInstrument $instrument): void
    {
        abort_unless(
            $user->hasPermission('pedagogical-instruments.ai-workspace')
                && $instrument->workflow_status === InstrumentWorkflowStatus::Draft
                && (int) $instrument->owner_user_id === (int) $user->id
                && (int) $instrument->created_by === (int) $user->id,
            404,
        );
    }

    private function assertAcademicContext(School $school, CourseSection $course, int $subjectId): void
    {
        $errors = [];
        if (! DB::table('lcd_school_academic_years')
            ->where('school_id', $school->id)
            ->where('academic_year_id', $course->academic_year_id)
            ->where('active', true)
            ->exists()) {
            $errors['course_id'] = 'El curso no pertenece al año académico activo del establecimiento.';
        }
        if (! ScheduleSubject::query()->whereKey($subjectId)->where('active', true)->exists()) {
            $errors['subject_id'] = 'La asignatura seleccionada no está activa.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function titleFromUpload(string $filename): string
    {
        $title = trim((string) Str::of(pathinfo($filename, PATHINFO_FILENAME))
            ->replace(['_', '-'], ' ')
            ->squish());

        return Str::limit($title !== '' ? $title : 'Instrumento pedagógico', 191, '');
    }
}
