<?php

namespace App\Services\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\SubjectCatalogProfile;
use App\Models\LibroDigital\SubjectCurriculumLink;
use App\Models\LibroDigital\TeacherAssignment;
use App\Models\Schedule\ScheduleEvent;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OfficialCurriculumSyncService
{
    public function __construct(
        private readonly CurriculumGradeResolver $grades,
        private readonly BookProvisioningService $provisioning,
        private readonly RegulatoryProfileResolver $profiles,
        private readonly CompliancePreflightService $preflight,
        private readonly WorkflowStateMachine $workflows,
        private readonly AuditEventWriter $audit,
    ) {}

    /** @return array<string, mixed> */
    public function preview(School $school, AcademicYear $year): array
    {
        $context = $this->context($school, $year);

        return $this->summary($context, true);
    }

    /** @return array<string, mixed> */
    public function execute(School $school, AcademicYear $year, User $actor, string $approvalReference): array
    {
        $context = $this->context($school, $year);
        $correlationId = (string) Str::ulid();
        $subjectIds = $context['subjects']->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $inactiveIds = $context['subjects']->where('active', false)->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $linksCreated = 0;
        $linksReactivated = 0;

        DB::transaction(function () use ($context, $subjectIds, $inactiveIds, $actor, &$linksCreated, &$linksReactivated): void {
            DB::table('lcd_schools')->where('id', $context['school']->id)->lockForUpdate()->first();
            if ($inactiveIds->isNotEmpty()) {
                ScheduleSubject::query()->whereIn('id', $inactiveIds)->update(['active' => true, 'updated_at' => now()]);
            }

            $existingProfiles = SubjectCatalogProfile::query()->whereIn('schedule_subject_id', $subjectIds)->get()->keyBy('schedule_subject_id');
            $profileRows = [];
            foreach ($context['subjects'] as $subject) {
                $educationTypes = $context['rows']->where('schedule_subject_id', $subject->id)
                    ->pluck('grade_code')
                    ->map(fn (string $grade): string => $this->educationType($grade))
                    ->unique()->sort()->values()->all();
                $profile = $existingProfiles->get($subject->id);
                $profileRows[] = [
                    'schedule_subject_id' => $subject->id,
                    'display_name' => $profile?->display_name ?: $subject->name,
                    'subject_type' => 'official',
                    'description' => $profile?->description ?: 'Asignatura o núcleo oficial con cobertura en el Currículum Nacional vigente.',
                    'education_types' => json_encode($educationTypes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_by' => $profile?->created_by ?? $actor->id,
                    'updated_by' => $actor->id,
                    'created_at' => $profile?->created_at ?? now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('lcd_subject_catalog_profiles')->upsert(
                $profileRows,
                ['schedule_subject_id'],
                ['display_name', 'subject_type', 'description', 'education_types', 'updated_by', 'updated_at'],
            );

            $linkRows = [];
            foreach ($context['pairs'] as $pair) {
                $linkRows[] = [
                    'school_id' => $context['school']->id,
                    'academic_year_id' => $context['year']->id,
                    'schedule_subject_id' => $pair['subject']->id,
                    'curriculum_catalog_id' => $context['activation']->curriculum_catalog_id,
                    'scope_key' => $pair['level_code'].':'.$pair['grade_code'].':'.$pair['curriculum_track'],
                    'level_code' => $pair['level_code'],
                    'grade_code' => $pair['grade_code'],
                    'curriculum_track' => $pair['curriculum_track'],
                    'valid_from' => $context['year']->starts_at,
                    'valid_to' => $context['year']->ends_at,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $existingLinks = SubjectCurriculumLink::query()
                ->where('school_id', $context['school']->id)
                ->where('academic_year_id', $context['year']->id)
                ->where('curriculum_catalog_id', $context['activation']->curriculum_catalog_id)
                ->whereIn('schedule_subject_id', $subjectIds)
                ->get()
                ->keyBy(fn (SubjectCurriculumLink $link): string => $link->schedule_subject_id.':'.$link->scope_key);
            foreach ($linkRows as $row) {
                $key = $row['schedule_subject_id'].':'.$row['scope_key'];
                $existing = $existingLinks->get($key);
                $linksCreated += (int) ($existing === null);
                $linksReactivated += (int) ($existing !== null && ! $existing->active);
            }
            if ($linkRows !== []) {
                DB::table('lcd_subject_curriculum_links')->upsert(
                    $linkRows,
                    ['school_id', 'academic_year_id', 'schedule_subject_id', 'curriculum_catalog_id', 'scope_key'],
                    ['level_code', 'grade_code', 'curriculum_track', 'valid_from', 'valid_to', 'active', 'updated_at'],
                );
            }
        }, 3);

        $this->audit->write(
            'lcd.official_curriculum.offer_synced',
            'activate_official_offer',
            $school,
            actor: $actor,
            schoolId: $school->id,
            academicYearId: $year->id,
            before: ['inactive_subject_ids' => $inactiveIds->all()],
            after: [
                'official_subject_ids' => $subjectIds->all(),
                'activated_subject_count' => $inactiveIds->count(),
                'links_created' => $linksCreated,
                'links_reactivated' => $linksReactivated,
                'source_urls_hash' => hash('sha256', implode('|', $context['source_urls'])),
                'approval_reference_hash' => hash('sha256', $approvalReference),
            ],
            reason: 'Oferta oficial confirmada por la institución desde las cuatro páginas de Currículum Nacional.',
            entityRevision: 1,
            correlationId: $correlationId,
        );

        $teacherMap = $this->teacherMap($context['pairs'], $year);
        $globalChecks = $this->preflight->run($school->id);
        $created = 0;
        $opened = 0;
        $alreadyOpen = 0;
        $blocked = [];

        foreach ($context['pairs'] as $pair) {
            $key = $pair['key'];
            $book = $context['books']->get($key);
            $wasCreated = false;
            if (! $book) {
                $teacher = $teacherMap->get($key);
                $profile = $this->profiles->resolve(
                    $year->starts_at ?? now()->toDateString(),
                    (string) $pair['course']->educationLevel->type,
                    (string) $pair['course']->educationLevel->type,
                );
                $book = $this->provisioning->provision(
                    $school,
                    $year,
                    $pair['course'],
                    $pair['subject'],
                    $profile,
                    $actor,
                    $teacher instanceof Staff ? $teacher : null,
                    ['notes' => 'Preparado desde la oferta oficial confirmada de Currículum Nacional.'],
                );
                $wasCreated = true;
                $created++;
                $this->audit->write(
                    'lcd.book.created_by_official_curriculum_sync',
                    'create',
                    $book,
                    actor: $actor,
                    schoolId: $school->id,
                    academicYearId: $year->id,
                    after: ['course_section_id' => $pair['course']->id, 'schedule_subject_id' => $pair['subject']->id, 'status' => 'draft'],
                    reason: 'Libro preparado desde oferta oficial confirmada.',
                    entityRevision: $book->revision,
                    correlationId: $correlationId,
                );
            }

            $book->loadMissing('teachingGroups.teacherAssignments');
            $this->assignTeacherFromSchedule($book, $teacherMap->get($key), $actor, $year, $correlationId);
            $status = $this->status($book->fresh());
            if ($status === 'open') {
                $alreadyOpen++;

                continue;
            }

            $checks = $this->bookChecks($book->fresh(), $globalChecks);
            if (! $checks['ready']) {
                $blocked[] = [
                    'book_id' => $book->id,
                    'course' => $pair['course']->display_name,
                    'subject' => $pair['subject']->resolvedDisplayName(),
                    'created' => $wasCreated,
                    'blockers' => collect($checks['checks'])->where('passed', false)->pluck('code')->values()->all(),
                ];

                continue;
            }
            if (! in_array($status, ['draft', 'pending_preflight'], true)) {
                $blocked[] = [
                    'book_id' => $book->id,
                    'course' => $pair['course']->display_name,
                    'subject' => $pair['subject']->resolvedDisplayName(),
                    'created' => $wasCreated,
                    'blockers' => ['invalid_status_'.$status],
                ];

                continue;
            }

            $from = $status;
            DB::transaction(function () use ($book, $actor): void {
                $locked = Book::query()->lockForUpdate()->findOrFail($book->id);
                $status = $this->status($locked);
                if ($status === 'draft') {
                    $this->workflows->assertCan('book', 'draft', 'pending_preflight');
                    $locked->forceFill([
                        'status' => 'pending_preflight',
                        'revision' => ((int) $locked->revision) + 1,
                        'lock_version' => ((int) $locked->lock_version) + 1,
                        'updated_by' => $actor->id,
                    ])->save();
                    $status = 'pending_preflight';
                }
                $this->workflows->assertCan('book', $status, 'open');
                $locked->forceFill([
                    'status' => 'open',
                    'opened_at' => now('UTC'),
                    'opened_by' => $actor->id,
                    'revision' => ((int) $locked->revision) + 1,
                    'lock_version' => ((int) $locked->lock_version) + 1,
                    'updated_by' => $actor->id,
                ])->save();
            }, 3);
            $fresh = $book->fresh();
            $this->audit->write(
                'lcd.book.opened_by_official_curriculum_sync',
                'open',
                $fresh,
                actor: $actor,
                schoolId: $school->id,
                academicYearId: $year->id,
                before: ['status' => $from],
                after: ['status' => 'open', 'approval_reference_hash' => hash('sha256', $approvalReference)],
                reason: 'Apertura masiva con nómina sellada y asignación docente inequívoca.',
                entityRevision: $fresh->revision,
                correlationId: $correlationId,
            );
            $opened++;
        }

        return [
            ...$this->summary($this->context($school, $year), false),
            'dry_run' => false,
            'activated_subjects' => $inactiveIds->count(),
            'links_created' => $linksCreated,
            'links_reactivated' => $linksReactivated,
            'books_created' => $created,
            'books_opened' => $opened,
            'books_already_open' => $alreadyOpen,
            'books_blocked' => count($blocked),
            'blocked' => $blocked,
            'correlation_id' => $correlationId,
        ];
    }

    /** @return array<string, mixed> */
    private function context(School $school, AcademicYear $year): array
    {
        $activation = CurriculumCatalogActivation::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->where('status', CurriculumCatalogActivation::STATUS_ACTIVATED)
            ->whereHas('curriculumCatalog', fn ($query) => $query->where('active', true))
            ->latest('activation_version')
            ->first();
        if (! $activation) {
            throw new RuntimeException('No existe un catálogo curricular oficial activado para el establecimiento y año seleccionados.');
        }

        $allowed = $this->allowedScopes();
        $rows = DB::table('lcd_learning_objectives as objectives')
            ->join('schedule_subjects as subjects', 'subjects.id', '=', 'objectives.schedule_subject_id')
            ->where('objectives.curriculum_catalog_id', $activation->curriculum_catalog_id)
            ->where('objectives.active', true)
            ->whereNotNull('objectives.schedule_subject_id')
            ->whereIn('objectives.grade_code', array_keys($allowed))
            ->select([
                'objectives.schedule_subject_id',
                'objectives.grade_code',
                'objectives.level_code',
                'objectives.curriculum_track',
            ])
            ->distinct()
            ->get()
            ->filter(fn ($row): bool => in_array((string) $row->curriculum_track, $allowed[(string) $row->grade_code] ?? [], true))
            ->values();
        if ($rows->isEmpty()) {
            throw new RuntimeException('El catálogo activado no contiene asignaturas oficiales dentro de los cuatro tramos confirmados.');
        }

        $subjects = ScheduleSubject::query()
            ->with('catalogProfile')
            ->whereIn('id', $rows->pluck('schedule_subject_id')->unique())
            ->orderBy('name')
            ->get();
        $subjectsById = $subjects->keyBy('id');
        $courses = CourseSection::query()->with('educationLevel')
            ->where('academic_year_id', $year->id)
            ->where('active', true)
            ->orderBy('id')
            ->get();
        $pairs = collect();
        foreach ($courses as $course) {
            $grade = $this->grades->fromEducationLevel($course->educationLevel);
            if ($grade === null) {
                continue;
            }
            foreach ($rows->where('grade_code', $grade) as $row) {
                $subject = $subjectsById->get((int) $row->schedule_subject_id);
                if (! $subject) {
                    continue;
                }
                $pairs->push([
                    'key' => $course->id.':'.$subject->id,
                    'course' => $course,
                    'subject' => $subject,
                    'grade_code' => (string) $row->grade_code,
                    'level_code' => (string) $row->level_code,
                    'curriculum_track' => (string) $row->curriculum_track,
                ]);
            }
        }
        $pairs = $pairs->unique('key')->sortBy('key')->values();

        $books = Book::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->where('status', '<>', 'archived')
            ->whereIn('course_section_id', $courses->pluck('id'))
            ->with(['teachingGroups.teacherAssignments', 'teachingGroups.rosterSnapshots'])
            ->get()
            ->mapWithKeys(function (Book $book): array {
                $group = $book->teachingGroups->first();

                return $group ? [$book->course_section_id.':'.$group->schedule_subject_id => $book] : [];
            })
            ->toBase();

        return [
            'school' => $school,
            'year' => $year,
            'activation' => $activation,
            'rows' => $rows,
            'subjects' => $subjects,
            'courses' => $courses,
            'pairs' => $pairs,
            'books' => $books,
            'source_urls' => collect(config('libro_digital_official_curriculum.sources', []))->pluck('url')->values()->all(),
        ];
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function summary(array $context, bool $dryRun): array
    {
        $expectedKeys = $context['pairs']->pluck('key');
        $expectedBooks = $context['books']->only($expectedKeys->all());

        return [
            'dry_run' => $dryRun,
            'school' => ['id' => $context['school']->id, 'rbd' => $context['school']->rbd, 'name' => $context['school']->name],
            'academic_year' => ['id' => $context['year']->id, 'year' => $context['year']->year],
            'catalog_activation_id' => $context['activation']->id,
            'source_urls' => $context['source_urls'],
            'official_subjects' => $context['subjects']->count(),
            'inactive_official_subjects' => $context['subjects']->where('active', false)->count(),
            'active_courses' => $context['courses']->count(),
            'expected_books' => $context['pairs']->count(),
            'existing_expected_books' => $expectedBooks->count(),
            'open_expected_books' => $expectedBooks->filter(fn (Book $book): bool => $this->status($book) === 'open')->count(),
            'missing_expected_books' => $context['pairs']->count() - $expectedBooks->count(),
        ];
    }

    /** @return array<string, list<string>> */
    private function allowedScopes(): array
    {
        $allowed = [];
        foreach ((array) config('libro_digital_official_curriculum.sources', []) as $source) {
            foreach ((array) ($source['grade_codes'] ?? []) as $grade) {
                $allowed[(string) $grade] = array_values(array_unique([
                    ...($allowed[(string) $grade] ?? []),
                    ...array_map('strval', (array) ($source['tracks'] ?? [])),
                ]));
            }
        }

        return $allowed;
    }

    /** @param Collection<int, array<string, mixed>> $pairs @return Collection<string, Staff> */
    private function teacherMap(Collection $pairs, AcademicYear $year): Collection
    {
        $courseIds = $pairs->pluck('course.id')->unique()->values();
        $subjectIds = $pairs->pluck('subject.id')->unique()->values();
        if ($courseIds->isEmpty() || $subjectIds->isEmpty()) {
            return collect();
        }

        $candidates = ScheduleEvent::query()
            ->join('staff', 'staff.id', '=', 'schedule_events.staff_id')
            ->where('schedule_events.academic_year_id', $year->id)
            ->where('schedule_events.status', ScheduleEvent::STATUS_CONFIRMED)
            ->where('staff.active', true)
            ->whereIn('schedule_events.course_section_id', $courseIds)
            ->whereIn('schedule_events.schedule_subject_id', $subjectIds)
            ->distinct()
            ->get([
                'schedule_events.course_section_id',
                'schedule_events.schedule_subject_id',
                'schedule_events.staff_id',
            ])
            ->groupBy(fn ($row): string => $row->course_section_id.':'.$row->schedule_subject_id);
        $staff = Staff::query()->with('user')->whereIn('id', $candidates->flatten()->pluck('staff_id')->unique())->get()->keyBy('id');

        return $candidates->map(function (Collection $rows) use ($staff): ?Staff {
            $ids = $rows->pluck('staff_id')->unique()->values();

            return $ids->count() === 1 ? $staff->get((int) $ids->first()) : null;
        })->filter();
    }

    private function assignTeacherFromSchedule(Book $book, mixed $candidate, User $actor, AcademicYear $year, string $correlationId): void
    {
        if (! $candidate instanceof Staff || $book->teachingGroups()->whereHas('teacherAssignments', fn ($query) => $query->where('active', true))->exists()) {
            return;
        }
        $group = $book->teachingGroups()->first();
        if (! $group) {
            return;
        }
        $candidate->loadMissing('user');
        $assignment = TeacherAssignment::query()->create([
            'school_id' => $book->school_id,
            'academic_year_id' => $book->academic_year_id,
            'book_id' => $book->id,
            'teaching_group_id' => $group->id,
            'staff_id' => $candidate->id,
            'user_id' => $candidate->user?->id,
            'schedule_subject_id' => $group->schedule_subject_id,
            'teacher_name_snapshot' => $candidate->full_name,
            'valid_from' => $year->starts_at ?? now()->toDateString(),
            'valid_to' => $year->ends_at,
            'is_primary' => true,
            'active' => true,
            'assigned_by' => $actor->id,
        ]);
        $this->audit->write(
            'lcd.teacher_assignment.created_from_confirmed_schedule',
            'assign',
            $assignment,
            actor: $actor,
            schoolId: $book->school_id,
            academicYearId: $book->academic_year_id,
            after: ['book_id' => $book->id, 'teaching_group_id' => $group->id, 'staff_id' => $candidate->id],
            reason: 'Asignación inequívoca derivada de un horario confirmado.',
            correlationId: $correlationId,
        );
    }

    /** @return array<string, mixed> */
    private function bookChecks(Book $book, array $globalChecks): array
    {
        $checks = $globalChecks;
        $bookChecks = [
            ['code' => 'sealed_roster', 'passed' => $book->teachingGroups()->whereHas('rosterSnapshots', fn ($query) => $query->where('status', 'sealed'))->exists()],
            ['code' => 'teacher_assignment', 'passed' => $book->teachingGroups()->whereHas('teacherAssignments', fn ($query) => $query->where('active', true))->exists()],
        ];
        $checks['checks'] = [...$checks['checks'], ...$bookChecks];
        $checks['ready'] = (bool) $checks['ready'] && collect($bookChecks)->every('passed');

        return $checks;
    }

    private function educationType(string $grade): string
    {
        return match (true) {
            str_starts_with($grade, 'NT') => 'parvularia',
            str_ends_with($grade, 'B') => 'basica',
            default => 'media',
        };
    }

    private function status(Book $book): string
    {
        return $book->status instanceof \BackedEnum ? (string) $book->status->value : (string) $book->status;
    }
}
