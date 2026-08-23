<?php

namespace App\Services\Grades;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Grades\AnnualGradeImport;
use App\Models\Grades\AnnualGradeImportRow;
use App\Models\LibroDigital\Assessment;
use App\Models\LibroDigital\AssessmentPeriod;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\GradingScheme;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\StudentResult;
use App\Models\LibroDigital\SubjectExternalAlias;
use App\Models\Schedule\ScheduleSubject;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\LibroDigital\CanonicalJson;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnnualGradeImportService
{
    private const SUBJECT_ALIASES = [
        'artes musicales' => 'musica',
        'ed fisica' => 'educacion fisica y salud',
        'idioma extranjero ingles' => 'ingles',
        'quimica profundizacion' => 'quimica',
        'religion catolica' => 'religion',
    ];

    public function __construct(
        private readonly AnnualGradeWorkbookParser $parser,
        private readonly CanonicalJson $canonical,
    ) {}

    /** @return array{import:AnnualGradeImport,duplicate:bool} */
    public function import(UploadedFile $file, AcademicYear $academicYear, School $school, User $actor): array
    {
        if (! $school->academicYears()->whereKey($academicYear->id)->wherePivot('active', true)->exists()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'El año académico no está habilitado para el establecimiento seleccionado.',
            ]);
        }

        $parsed = $this->parser->parse($file->getRealPath());
        if ((int) $parsed['year'] !== (int) $academicYear->year) {
            throw ValidationException::withMessages([
                'file' => "El Excel corresponde a {$parsed['year']} y seleccionaste el año {$academicYear->year}.",
            ]);
        }

        $checksum = (string) hash_file('sha256', $file->getRealPath());
        $existing = AnnualGradeImport::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('checksum', $checksum)
            ->first();
        if ($existing) {
            $this->retry($existing, $actor);

            return ['import' => $existing->refresh(), 'duplicate' => true];
        }

        $disk = (string) config('grades.imports_disk', 'local');
        $directory = trim((string) config('grades.imports_path', 'grades/imports'), '/')
            .'/'.$school->public_id.'/'.$academicYear->year;
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'xlsx';
        $storedPath = $file->storeAs($directory, Str::uuid().'.'.$extension, $disk);

        try {
            $import = DB::transaction(function () use ($file, $academicYear, $school, $actor, $parsed, $checksum, $storedPath): AnnualGradeImport {
                $previous = AnnualGradeImport::query()
                    ->where('school_id', $school->id)
                    ->where('academic_year_id', $academicYear->id)
                    ->lockForUpdate()
                    ->get(['id', 'version']);
                $version = ((int) $previous->max('version')) + 1;
                $summary = $parsed['summary'];
                $import = AnnualGradeImport::query()->create([
                    'school_id' => $school->id,
                    'academic_year_id' => $academicYear->id,
                    'school_year' => $academicYear->year,
                    'version' => $version,
                    'status' => 'processing',
                    'source' => 'annual_grade_excel',
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize() ?: 0,
                    'checksum' => $checksum,
                    'source_date_from' => $parsed['date_from'],
                    'source_date_to' => $parsed['date_to'],
                    'sheet_count' => count($parsed['sheets']),
                    'course_count' => $summary['courses'],
                    'student_rows' => $summary['students'],
                    'column_count' => $summary['columns'],
                    'cell_count' => $summary['cells'],
                    'numeric_cells' => $summary['numeric'],
                    'pending_cells' => $summary['pending'],
                    'not_applicable_cells' => $summary['not_applicable'],
                    'invalid_cells' => $summary['invalid'],
                    'metadata' => [
                        'sheets' => $parsed['sheets'],
                        'source_summary' => $summary,
                        'assessment_date_semantics' => 'source_report_cutoff',
                        'pending_symbols' => ['P', 'PENDIENTE', 'blank'],
                        'not_applicable_symbols' => ['-', '—', 'N/A'],
                    ],
                    'created_by' => $actor->id,
                ]);

                $this->insertParsedRows($import, $academicYear, $parsed['courses'], $actor);
                $this->insertParsedColumns($import, $parsed['courses']);
                $this->insertParsedCells($import, $file->getRealPath());
                $this->reconcileLocked($import, $actor);

                return $import;
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($storedPath);
            throw $exception;
        }

        return ['import' => $import->refresh(), 'duplicate' => false];
    }

    public function retry(AnnualGradeImport $import, User $actor): AnnualGradeImport
    {
        DB::transaction(function () use ($import, $actor): void {
            $locked = AnnualGradeImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->reconcileLocked($locked, $actor);
        }, 3);

        return $import->refresh();
    }

    public function resolve(AnnualGradeImportRow $row, int $studentProfileId, ?string $note, User $actor): AnnualGradeImportRow
    {
        $importId = (int) $row->annual_grade_import_id;
        DB::transaction(function () use ($row, $studentProfileId, $note, $actor): void {
            $locked = AnnualGradeImportRow::query()->with('annualImport')->lockForUpdate()->findOrFail($row->id);
            $import = $locked->annualImport;
            $enrollment = StudentEnrollment::query()
                ->where('academic_year_id', $import->academic_year_id)
                ->where('student_profile_id', $studentProfileId)
                ->when($locked->course_section_id, fn ($query, $courseId) => $query->where('course_section_id', $courseId))
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
                ->first();
            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'student_profile_id' => 'La estudiante no tiene una matrícula compatible con el año y curso de la fila.',
                ]);
            }
            $duplicate = AnnualGradeImportRow::query()
                ->where('annual_grade_import_id', $import->id)
                ->where('course_section_id', $enrollment->course_section_id)
                ->where('student_profile_id', $studentProfileId)
                ->whereKeyNot($locked->id)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'student_profile_id' => 'La estudiante ya fue vinculada a otra fila del mismo curso en esta carga.',
                ]);
            }

            $locked->forceFill([
                'course_section_id' => $enrollment->course_section_id,
                'student_profile_id' => $studentProfileId,
                'student_enrollment_id' => $enrollment->id,
                'match_status' => 'manual',
                'match_confidence' => 100,
                'resolution_note' => $note,
                'matched_by' => $actor->id,
                'matched_at' => now(),
            ])->save();
        }, 3);

        $this->retry(AnnualGradeImport::query()->findOrFail($importId), $actor);

        return $row->refresh()->load(['student:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name']);
    }

    /** @return list<array<string,mixed>> */
    public function candidates(AnnualGradeImportRow $row, string $search): array
    {
        $row->loadMissing('annualImport');
        $query = StudentEnrollment::query()
            ->with(['studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name'])
            ->where('academic_year_id', $row->annualImport->academic_year_id)
            ->when($row->course_section_id, fn ($builder, $courseId) => $builder->where('course_section_id', $courseId))
            ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
            ->whereHas('studentProfile', function ($builder) use ($search): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';
                $builder->where(function ($identity) use ($term): void {
                    $identity->where('registered_name', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('rut', 'like', $term);
                });
            })
            ->limit(30)
            ->get();

        $used = AnnualGradeImportRow::query()
            ->where('annual_grade_import_id', $row->annual_grade_import_id)
            ->whereKeyNot($row->id)
            ->whereNotNull('student_profile_id')
            ->pluck('student_profile_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true]);

        return $this->candidatePayload($row->source_name, $row->normalized_rut, $query->reject(
            fn (StudentEnrollment $enrollment): bool => $used->has((int) $enrollment->student_profile_id)
        ))->take(20)->values()->all();
    }

    /** @param list<array<string,mixed>> $courses */
    private function insertParsedRows(AnnualGradeImport $import, AcademicYear $year, array $courses, User $actor): void
    {
        $courseMap = CourseSection::query()->where('academic_year_id', $year->id)->get(['id', 'display_name'])
            ->keyBy(fn (CourseSection $course): string => $this->parser->normalizeCourse($course->display_name));
        $enrollments = StudentEnrollment::query()
            ->with(['studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name'])
            ->where('academic_year_id', $year->id)
            ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
            ->get();
        $rutMap = $enrollments->filter(fn (StudentEnrollment $enrollment): bool => filled($enrollment->studentProfile?->rut))
            ->groupBy(fn (StudentEnrollment $enrollment): string => $this->parser->normalizeRut((string) $enrollment->studentProfile?->rut));
        $enrollmentMap = $enrollments->keyBy(fn (StudentEnrollment $enrollment): string => $enrollment->course_section_id.'|'.$enrollment->student_profile_id);
        $priorManualMatches = AnnualGradeImportRow::query()
            ->where('annual_grade_import_id', '!=', $import->id)
            ->whereNotNull('student_profile_id')
            ->whereIn('match_status', ['manual', 'carried_manual'])
            ->whereHas('annualImport', fn ($query) => $query
                ->where('school_id', $import->school_id)
                ->where('academic_year_id', $year->id))
            ->latest('annual_grade_import_id')
            ->get([
                'annual_grade_import_id', 'course_section_id', 'student_profile_id',
                'source_course_name', 'source_name', 'normalized_rut',
            ]);
        $priorMatchMap = collect();
        foreach ($priorManualMatches as $prior) {
            $keys = array_filter([
                $prior->normalized_rut
                    ? $this->manualMatchKey($this->parser->normalizeCourse($prior->source_course_name), 'rut', $prior->normalized_rut)
                    : null,
                $this->manualMatchKey(
                    $this->parser->normalizeCourse($prior->source_course_name),
                    'name',
                    $this->parser->normalizeName($prior->source_name),
                ),
            ]);
            foreach ($keys as $key) {
                if (! $priorMatchMap->has($key)) {
                    $priorMatchMap->put($key, collect());
                }
                $priorMatchMap->get($key)->push($prior);
            }
        }

        $initial = [];
        $usedStudentIds = [];
        foreach ($courses as $course) {
            $matchedCourse = $courseMap->get($course['normalized_course']);
            foreach ($course['students'] as $student) {
                $rutMatches = $rutMap->get($student['normalized_rut'], collect());
                $rutEnrollment = $rutMatches->count() === 1 ? $rutMatches->first() : null;
                $courseMatches = $matchedCourse && $rutEnrollment
                    && (int) $rutEnrollment->course_section_id === (int) $matchedCourse->id;
                $carried = null;
                $carriedEnrollment = null;
                if ($matchedCourse && ! $courseMatches) {
                    $priorCandidates = collect([
                        $student['normalized_rut']
                            ? $priorMatchMap->get($this->manualMatchKey($course['normalized_course'], 'rut', $student['normalized_rut']), collect())
                            : collect(),
                        $priorMatchMap->get($this->manualMatchKey(
                            $course['normalized_course'],
                            'name',
                            $this->parser->normalizeName($student['source_name']),
                        ), collect()),
                    ])->flatten(1)
                        ->unique(fn (AnnualGradeImportRow $row): string => $row->student_profile_id.'|'.$row->course_section_id)
                        ->filter(fn (AnnualGradeImportRow $row): bool => (int) $row->course_section_id === (int) $matchedCourse->id);
                    if ($priorCandidates->count() === 1) {
                        $carried = $priorCandidates->first();
                        $carriedEnrollment = $enrollmentMap->get($matchedCourse->id.'|'.$carried->student_profile_id);
                    }
                }
                $matchStatus = match (true) {
                    ! $matchedCourse => 'course_not_found',
                    $rutMatches->count() > 1 => 'duplicate_rut',
                    $courseMatches => 'exact_rut',
                    (bool) $carriedEnrollment => 'carried_manual',
                    (bool) $rutEnrollment => 'course_conflict',
                    default => 'unmatched',
                };
                $matchedEnrollment = $courseMatches ? $rutEnrollment : $carriedEnrollment;
                $studentId = $matchedEnrollment ? (int) $matchedEnrollment->student_profile_id : null;
                if ($studentId) {
                    $usedStudentIds[$studentId] = true;
                }
                $initial[] = compact('course', 'student', 'matchedCourse', 'matchedEnrollment', 'matchStatus', 'studentId', 'carried');
            }
        }

        $payloads = [];
        $now = now();
        foreach ($initial as $item) {
            $coursePool = $item['matchedCourse']
                ? $enrollments->where('course_section_id', $item['matchedCourse']->id)
                : collect();
            $candidates = $this->candidatePayload(
                $item['student']['source_name'],
                $item['student']['normalized_rut'],
                $coursePool->reject(fn (StudentEnrollment $enrollment): bool => isset($usedStudentIds[$enrollment->student_profile_id]) && (int) $enrollment->student_profile_id !== (int) $item['studentId']),
            )->take(5)->values()->all();
            $payloads[] = [
                'annual_grade_import_id' => $import->id,
                'course_section_id' => $item['matchedCourse']?->id,
                'student_profile_id' => $item['studentId'],
                'student_enrollment_id' => $item['matchedEnrollment']?->id,
                'source_sheet' => $item['course']['source_sheet'],
                'source_row' => $item['student']['source_row'],
                'source_course_name' => $item['course']['source_course_name'],
                'normalized_course' => $item['course']['normalized_course'],
                'list_number' => $item['student']['list_number'],
                'source_name' => $item['student']['source_name'],
                'source_rut' => $item['student']['source_rut'],
                'normalized_rut' => $item['student']['normalized_rut'],
                'match_status' => $item['matchStatus'],
                'match_confidence' => $item['studentId'] ? 100 : ($candidates[0]['score'] ?? null),
                'candidates' => json_encode($candidates, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'resolution_note' => $item['carried']
                    ? 'Match manual reutilizado desde la importación #'.$item['carried']->annual_grade_import_id.'.'
                    : null,
                'matched_by' => $item['carried'] ? $actor->id : null,
                'matched_at' => $item['studentId'] ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($payloads, 400) as $chunk) {
            DB::table('annual_grade_import_rows')->insert($chunk);
        }
    }

    private function manualMatchKey(string $normalizedCourse, string $type, string $identity): string
    {
        return $normalizedCourse.'|'.$type.'|'.$identity;
    }

    /** @param list<array<string,mixed>> $courses */
    private function insertParsedColumns(AnnualGradeImport $import, array $courses): void
    {
        $payloads = [];
        $now = now();
        foreach ($courses as $course) {
            foreach ($course['columns'] as $column) {
                $payloads[] = [
                    'annual_grade_import_id' => $import->id,
                    'source_sheet' => $course['source_sheet'],
                    'source_column' => $column['source_column'],
                    'source_course_name' => $course['source_course_name'],
                    'normalized_course' => $course['normalized_course'],
                    'source_subject_name' => $column['source_subject_name'],
                    'normalized_subject' => $column['normalized_subject'],
                    'source_header' => $column['source_header'],
                    'normalized_header' => $column['normalized_header'],
                    'header_occurrence' => $column['header_occurrence'],
                    'subject_ordinal' => $column['subject_ordinal'],
                    'mapping_status' => 'pending',
                    'cell_count' => array_sum($column['counts']),
                    'numeric_count' => $column['counts']['numeric'],
                    'pending_count' => $column['counts']['pending'],
                    'not_applicable_count' => $column['counts']['not_applicable'],
                    'invalid_count' => $column['counts']['invalid'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($payloads, 400) as $chunk) {
            DB::table('annual_grade_import_columns')->insert($chunk);
        }
    }

    private function insertParsedCells(AnnualGradeImport $import, string $path): void
    {
        $rowIds = DB::table('annual_grade_import_rows')->where('annual_grade_import_id', $import->id)->get(['id', 'source_sheet', 'source_row'])
            ->keyBy(fn ($row): string => $row->source_sheet.'|'.$row->source_row);
        $columnIds = DB::table('annual_grade_import_columns')->where('annual_grade_import_id', $import->id)->get(['id', 'source_sheet', 'source_column'])
            ->keyBy(fn ($column): string => $column->source_sheet.'|'.$column->source_column);
        $payloads = [];
        $now = now();
        $this->parser->eachCell($path, function (array $cell) use ($import, $rowIds, $columnIds, $now, &$payloads): void {
            $rowId = $rowIds[$cell['source_sheet'].'|'.$cell['source_row']]->id;
            $columnId = $columnIds[$cell['source_sheet'].'|'.$cell['source_column']]->id;
            $payloads[] = [
                'annual_grade_import_id' => $import->id,
                'annual_grade_import_row_id' => $rowId,
                'annual_grade_import_column_id' => $columnId,
                'source_value' => $cell['raw'],
                'value_kind' => $cell['kind'],
                'numeric_value' => $cell['numeric_value'],
                'apply_status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($payloads) >= 400) {
                DB::table('annual_grade_import_cells')->insert($payloads);
                $payloads = [];
            }
        });
        if ($payloads !== []) {
            DB::table('annual_grade_import_cells')->insert($payloads);
        }
    }

    private function reconcileLocked(AnnualGradeImport $import, User $actor): void
    {
        $year = AcademicYear::query()->findOrFail($import->academic_year_id);
        $school = School::query()->findOrFail($import->school_id);
        $this->resolveColumnMappings($import, $year, $school, $actor);
        $this->applyCells($import, $actor);
        $this->refreshSummary($import);
    }

    private function resolveColumnMappings(AnnualGradeImport $import, AcademicYear $year, School $school, User $actor): void
    {
        $columns = DB::table('annual_grade_import_columns')->where('annual_grade_import_id', $import->id)->orderBy('id')->get();
        $courseMap = CourseSection::query()->with('educationLevel:id,type')->where('academic_year_id', $year->id)->get(['id', 'display_name', 'education_level_id'])
            ->groupBy(fn (CourseSection $course): string => $this->parser->normalizeCourse($course->display_name));
        // La vigencia efectiva la determina el libro abierto. El catálogo histórico puede
        // contener asignaturas inactivas que siguen vinculadas a libros del año importado.
        $subjects = ScheduleSubject::query()->get(['id', 'name']);
        $subjectMap = $subjects->groupBy(fn (ScheduleSubject $subject): string => $this->parser->normalizeSubject($subject->name));
        $externalAliasMap = SubjectExternalAlias::query()
            ->with('subject:id,name')
            ->where('school_id', $school->id)
            ->where('source_system', 'legacy_gradebook')
            ->where('active', true)
            ->get()
            ->groupBy(fn (SubjectExternalAlias $alias): string => $alias->normalized_name.'|'.$alias->education_type);
        $books = Book::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->with(['teachingGroups.teacherAssignments'])
            ->get();
        $bookMap = collect();
        foreach ($books as $book) {
            foreach ($book->teachingGroups as $group) {
                $bookMap->push(['book' => $book, 'group' => $group, 'key' => $book->course_section_id.'|'.$group->schedule_subject_id]);
            }
        }
        $bookMap = $bookMap->groupBy('key');

        $resolved = [];
        foreach ($columns as $column) {
            $courseCandidates = $courseMap->get($column->normalized_course, collect());
            if ($courseCandidates->count() !== 1) {
                $resolved[$column->id] = $this->blockedColumn($column, $courseCandidates->isEmpty() ? 'course_not_found' : 'course_ambiguous', 'No existe un curso único para '.$column->source_course_name.'.');

                continue;
            }
            $course = $courseCandidates->first();
            $subject = $this->resolveSubject(
                (string) $column->source_subject_name,
                $subjectMap,
                $externalAliasMap,
                (string) ($course->educationLevel?->type ?? 'all'),
            );
            if (! $subject) {
                $resolved[$column->id] = $this->blockedColumn($column, 'subject_not_found', 'No se encontró una asignatura única para '.$column->source_subject_name.'.', ['course_section_id' => $course->id]);

                continue;
            }
            $contexts = $bookMap->get($course->id.'|'.$subject->id, collect());
            $openContexts = $contexts->filter(fn (array $context): bool => $this->enumValue($context['book']->status) === 'open');
            if ($openContexts->count() > 1) {
                $resolved[$column->id] = $this->blockedColumn($column, 'book_ambiguous', 'Existe más de un libro abierto para el curso y asignatura.', ['course_section_id' => $course->id, 'schedule_subject_id' => $subject->id]);

                continue;
            }
            if ($openContexts->isEmpty()) {
                $status = $contexts->isEmpty() ? 'book_not_found' : 'book_not_open';
                $message = $contexts->isEmpty()
                    ? 'No existe Libro Digital para el curso y asignatura.'
                    : 'El Libro Digital existe, pero no está abierto.';
                $resolved[$column->id] = $this->blockedColumn($column, $status, $message, ['course_section_id' => $course->id, 'schedule_subject_id' => $subject->id]);

                continue;
            }
            $context = $openContexts->first();
            $assignment = $context['group']->teacherAssignments
                ->where('active', true)
                ->filter(function ($item) use ($import): bool {
                    $on = $import->source_date_to?->toDateString();
                    if (! $on) {
                        return true;
                    }

                    return $item->valid_from?->toDateString() <= $on
                        && (! $item->valid_to || $item->valid_to->toDateString() >= $on);
                })
                ->sortByDesc('is_primary')
                ->first();

            $importKey = hash('sha256', implode('|', [
                'annual-grade', $school->id, $year->id, $course->id, $subject->id,
                $column->normalized_header, $column->header_occurrence,
            ]));
            $resolved[$column->id] = [
                ...$this->columnBase($column),
                'course_section_id' => $course->id,
                'schedule_subject_id' => $subject->id,
                'book_id' => $context['book']->id,
                'teaching_group_id' => $context['group']->id,
                'teacher_assignment_id' => $assignment?->id,
                'assessment_id' => null,
                'import_key' => $importKey,
                'mapping_status' => 'ready',
                'message' => $assignment
                    ? null
                    : 'Notas vinculadas por curso y asignatura; la asignación docente queda pendiente.',
                'updated_at' => now(),
            ];
        }

        $ready = collect($resolved)->where('mapping_status', 'ready');
        if ($ready->isNotEmpty()) {
            $scheme = $this->gradingScheme($school, $year);
            $periods = $this->assessmentPeriods($ready->pluck('book_id')->unique()->values(), $school, $year);
            $existing = Assessment::query()->whereIn('annual_import_key', $ready->pluck('import_key'))->get()->keyBy('annual_import_key');
            $missing = [];
            $now = now();
            foreach ($ready as $columnId => $mapping) {
                if ($existing->has($mapping['import_key'])) {
                    continue;
                }
                $source = $columns->firstWhere('id', $columnId);
                $period = $periods[(int) $mapping['book_id']];
                $label = $source->source_header.((int) $source->header_occurrence > 1 ? ' ('.$source->header_occurrence.')' : '');
                $missing[] = [
                    'public_id' => (string) Str::ulid(),
                    'school_id' => $school->id,
                    'book_id' => $mapping['book_id'],
                    'teaching_group_id' => $mapping['teaching_group_id'],
                    'assessment_period_id' => $period->id,
                    'grading_scheme_id' => $scheme->id,
                    'schedule_subject_id' => $mapping['schedule_subject_id'],
                    'teacher_assignment_id' => $mapping['teacher_assignment_id'],
                    'code' => Str::limit('IMP-'.Str::upper(str_replace(' ', '-', $source->normalized_header)).'-'.$source->header_occurrence, 80, ''),
                    'annual_import_key' => $mapping['import_key'],
                    'name' => $label,
                    'description' => 'Evaluación importada desde el reporte anual de calificaciones. La fecha corresponde al corte del archivo, no a la fecha original del instrumento.',
                    'assessment_type' => 'summative',
                    'assessment_date' => $import->source_date_to?->toDateString() ?: $year->ends_at?->toDateString(),
                    'weight' => 1,
                    'instrument_metadata' => json_encode([
                        'grading_scale' => '1_to_7',
                        'source' => 'annual_grade_excel',
                        'source_course' => $source->source_course_name,
                        'source_subject' => $source->source_subject_name,
                        'source_header' => $source->source_header,
                        'header_occurrence' => (int) $source->header_occurrence,
                        'subject_ordinal' => (int) $source->subject_ordinal,
                        'teacher_assignment_pending' => $mapping['teacher_assignment_id'] === null,
                        'teacher_attribution' => $mapping['teacher_assignment_id'] === null
                            ? 'unassigned_at_import'
                            : 'book_assignment_at_source_cutoff',
                        'assessment_date_semantics' => 'source_report_cutoff',
                        'weight_unknown' => true,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'status' => 'draft',
                    'revision' => 1,
                    'lock_version' => 1,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($missing, 300) as $chunk) {
                DB::table('lcd_assessments')->insertOrIgnore($chunk);
            }
            $assessments = Assessment::query()->whereIn('annual_import_key', $ready->pluck('import_key'))->get()->keyBy('annual_import_key');
            foreach ($ready as $columnId => $mapping) {
                $assessment = $assessments->get($mapping['import_key']);
                if (! $assessment) {
                    $resolved[$columnId] = $this->blockedColumn($columns->firstWhere('id', $columnId), 'assessment_failed', 'No fue posible preparar la evaluación de destino.');

                    continue;
                }
                if (in_array($this->enumValue($assessment->status), ['closed', 'amended', 'cancelled'], true)) {
                    $resolved[$columnId] = [
                        ...$mapping,
                        'assessment_id' => $assessment->id,
                        'mapping_status' => 'assessment_closed',
                        'message' => 'La evaluación importada está cerrada y solo admite una enmienda.',
                    ];

                    continue;
                }
                $resolved[$columnId]['assessment_id'] = $assessment->id;
                $resolved[$columnId]['mapping_status'] = 'mapped';
            }
        }

        foreach (array_chunk(array_values($resolved), 400) as $chunk) {
            DB::table('annual_grade_import_columns')->upsert(
                $chunk,
                ['id'],
                ['course_section_id', 'schedule_subject_id', 'book_id', 'teaching_group_id', 'teacher_assignment_id', 'assessment_id', 'import_key', 'mapping_status', 'message', 'updated_at'],
            );
        }
    }

    private function applyCells(AnnualGradeImport $import, User $actor): void
    {
        $now = now();
        DB::table('annual_grade_import_cells')->where('annual_grade_import_id', $import->id)->update([
            'apply_status' => 'waiting', 'message' => null, 'updated_at' => $now,
        ]);
        DB::table('annual_grade_import_cells')->where('annual_grade_import_id', $import->id)->where('value_kind', 'pending')->update([
            'apply_status' => 'pending', 'message' => 'Calificación pendiente en el archivo de origen.', 'updated_at' => $now,
        ]);
        DB::table('annual_grade_import_cells')->where('annual_grade_import_id', $import->id)->where('value_kind', 'invalid')->update([
            'apply_status' => 'invalid', 'message' => 'Valor no reconocido como nota, pendiente o no aplicable.', 'updated_at' => $now,
        ]);

        $unmatchedRows = DB::table('annual_grade_import_rows')->where('annual_grade_import_id', $import->id)->whereNull('student_profile_id')->pluck('id');
        foreach ($unmatchedRows->chunk(500) as $ids) {
            DB::table('annual_grade_import_cells')->whereIn('annual_grade_import_row_id', $ids)->update([
                'apply_status' => 'unmatched_student', 'message' => 'La fila requiere match manual de estudiante.', 'updated_at' => $now,
            ]);
        }
        $blockedColumns = DB::table('annual_grade_import_columns')->where('annual_grade_import_id', $import->id)->where('mapping_status', '!=', 'mapped')->get(['id', 'mapping_status', 'message']);
        foreach ($blockedColumns->groupBy('mapping_status') as $status => $items) {
            foreach ($items->pluck('id')->chunk(500) as $ids) {
                DB::table('annual_grade_import_cells')->whereIn('annual_grade_import_column_id', $ids)->update([
                    'apply_status' => $status, 'message' => $items->first()->message, 'updated_at' => $now,
                ]);
            }
        }

        $mappedColumns = DB::table('annual_grade_import_columns')
            ->where('annual_grade_import_id', $import->id)
            ->where('mapping_status', 'mapped')
            ->orderBy('id')
            ->get(['id', 'book_id', 'assessment_id']);
        $touchedAssessmentIds = collect();

        foreach ($mappedColumns->chunk(80) as $columnChunk) {
            $columnMap = $columnChunk->keyBy('id');
            $cells = DB::table('annual_grade_import_cells as c')
                ->join('annual_grade_import_rows as r', 'r.id', '=', 'c.annual_grade_import_row_id')
                ->whereIn('c.annual_grade_import_column_id', $columnChunk->pluck('id'))
                ->whereNotNull('r.student_profile_id')
                ->whereIn('c.value_kind', ['numeric', 'not_applicable'])
                ->get([
                    'c.id', 'c.annual_grade_import_id', 'c.annual_grade_import_row_id', 'c.annual_grade_import_column_id',
                    'c.source_value', 'c.value_kind', 'c.numeric_value', 'c.created_at',
                    'r.student_profile_id', 'r.student_enrollment_id',
                ]);
            if ($cells->isEmpty()) {
                continue;
            }
            $studentIds = $cells->pluck('student_profile_id')->unique()->values();
            $links = DB::table('lcd_enrollment_links')
                ->whereIn('book_id', $columnChunk->pluck('book_id')->unique())
                ->whereIn('student_profile_id', $studentIds)
                ->where('status', 'active')
                ->get(['id', 'book_id', 'student_profile_id', 'student_enrollment_id'])
                ->keyBy(fn ($link): string => $link->book_id.'|'.$link->student_profile_id);
            $assessmentIds = $columnChunk->pluck('assessment_id')->unique()->values();
            $existingResults = DB::table('lcd_student_results')
                ->whereIn('assessment_id', $assessmentIds)
                ->whereIn('student_profile_id', $studentIds)
                ->get()
                ->keyBy(fn ($result): string => $result->assessment_id.'|'.$result->student_profile_id);
            $assessments = DB::table('lcd_assessments')->whereIn('id', $assessmentIds)->get(['id', 'school_id', 'public_id', 'status'])->keyBy('id');

            $resultPayloads = [];
            $cellUpdates = [];
            $changedPairs = [];
            foreach ($cells as $cell) {
                $column = $columnMap[$cell->annual_grade_import_column_id];
                $assessment = $assessments[$column->assessment_id];
                $pairKey = $column->assessment_id.'|'.$cell->student_profile_id;
                $link = $links->get($column->book_id.'|'.$cell->student_profile_id);
                $baseCell = [
                    'id' => $cell->id,
                    'annual_grade_import_id' => $cell->annual_grade_import_id,
                    'annual_grade_import_row_id' => $cell->annual_grade_import_row_id,
                    'annual_grade_import_column_id' => $cell->annual_grade_import_column_id,
                    'source_value' => $cell->source_value,
                    'value_kind' => $cell->value_kind,
                    'numeric_value' => $cell->numeric_value,
                    'created_at' => $cell->created_at,
                    'updated_at' => $now,
                ];
                if (! $link) {
                    $cellUpdates[] = [...$baseCell, 'apply_status' => 'roster_missing', 'message' => 'La estudiante no forma parte de la nómina sellada del libro.', 'student_result_id' => null, 'applied_at' => null];

                    continue;
                }
                $existing = $existingResults->get($pairKey);
                if ($existing && ! $existing->annual_grade_import_id) {
                    $cellUpdates[] = [...$baseCell, 'apply_status' => 'preserved_manual', 'message' => 'Se conservó la calificación manual existente.', 'student_result_id' => $existing->id, 'applied_at' => $now];

                    continue;
                }

                $status = $cell->value_kind === 'not_applicable' ? 'exempt' : 'recorded';
                $numericValue = $cell->value_kind === 'numeric' ? (float) $cell->numeric_value : null;
                $exempt = $cell->value_kind === 'not_applicable';
                if ($existing
                    && $existing->status === $status
                    && (bool) $existing->exempt === $exempt
                    && (($numericValue === null && $existing->numeric_value === null) || abs((float) $existing->numeric_value - (float) $numericValue) < 0.0001)) {
                    $cellUpdates[] = [...$baseCell, 'apply_status' => 'unchanged', 'message' => 'La recarga coincide con el resultado ya importado.', 'student_result_id' => $existing->id, 'applied_at' => $now];

                    continue;
                }

                $revision = $existing ? ((int) $existing->revision) + 1 : 1;
                $record = [
                    'status' => $status,
                    'raw_score' => null,
                    'numeric_value' => $numericValue,
                    'qualitative_value' => null,
                    'normalized_percentage' => null,
                    'absent' => false,
                    'exempt' => $exempt,
                    'observation' => $exempt ? 'No aplica según reporte anual de origen (-).' : null,
                    'revision' => $revision,
                    'recorded_by' => $actor->id,
                    'recorded_at' => $now,
                ];
                $resultPayloads[] = [
                    'id' => $existing?->id,
                    'public_id' => $existing?->public_id ?: (string) Str::ulid(),
                    'assessment_id' => $column->assessment_id,
                    'student_profile_id' => $cell->student_profile_id,
                    'student_enrollment_id' => $link->student_enrollment_id,
                    'enrollment_link_id' => $link->id,
                    'annual_grade_import_id' => $import->id,
                    'annual_grade_import_cell_id' => $cell->id,
                    ...$record,
                    'record_hash' => $this->canonical->hash([
                        'assessment_public_id' => $assessment->public_id,
                        'student_profile_id' => $cell->student_profile_id,
                        ...$record,
                    ]),
                    'created_at' => $existing?->created_at ?: $now,
                    'updated_at' => $now,
                ];
                $changedPairs[$pairKey] = ['cell' => $cell, 'school_id' => $assessment->school_id, 'revision' => $revision];
                $touchedAssessmentIds->push((int) $column->assessment_id);
            }

            foreach (array_chunk($resultPayloads, 500) as $chunk) {
                $normalized = array_map(function (array $payload): array {
                    if ($payload['id'] === null) {
                        unset($payload['id']);
                    }

                    return $payload;
                }, $chunk);
                DB::table('lcd_student_results')->upsert(
                    $normalized,
                    ['assessment_id', 'student_profile_id'],
                    [
                        'student_enrollment_id', 'enrollment_link_id', 'annual_grade_import_id', 'annual_grade_import_cell_id',
                        'status', 'raw_score', 'numeric_value', 'qualitative_value', 'normalized_percentage', 'absent', 'exempt',
                        'observation', 'revision', 'record_hash', 'recorded_by', 'recorded_at', 'updated_at',
                    ],
                );
            }

            if ($changedPairs !== []) {
                $currentResults = DB::table('lcd_student_results')
                    ->whereIn('assessment_id', $assessmentIds)
                    ->whereIn('student_profile_id', $studentIds)
                    ->get()
                    ->keyBy(fn ($result): string => $result->assessment_id.'|'.$result->student_profile_id);
                $revisionPayloads = [];
                foreach ($changedPairs as $pairKey => $changed) {
                    $result = $currentResults[$pairKey];
                    $payload = [
                        'assessment_id' => $result->assessment_id,
                        'student_profile_id' => $result->student_profile_id,
                        'status' => $result->status,
                        'raw_score' => $result->raw_score,
                        'numeric_value' => $result->numeric_value,
                        'qualitative_value' => $result->qualitative_value,
                        'normalized_percentage' => $result->normalized_percentage,
                        'absent' => (bool) $result->absent,
                        'exempt' => (bool) $result->exempt,
                        'record_hash' => $result->record_hash,
                        'source' => 'annual_grade_excel',
                        'annual_grade_import_id' => $import->id,
                    ];
                    $revisionPayloads[] = [
                        'public_id' => (string) Str::ulid(),
                        'school_id' => $changed['school_id'],
                        'revisable_type' => StudentResult::class,
                        'revisable_id' => $result->id,
                        'revision' => $changed['revision'],
                        'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'payload_hash' => $this->canonical->hash($payload),
                        'reason' => 'annual_grade_excel_imported',
                        'created_by' => $actor->id,
                        'created_at' => $now,
                    ];
                    $cell = $changed['cell'];
                    $cellUpdates[] = [
                        'id' => $cell->id,
                        'annual_grade_import_id' => $cell->annual_grade_import_id,
                        'annual_grade_import_row_id' => $cell->annual_grade_import_row_id,
                        'annual_grade_import_column_id' => $cell->annual_grade_import_column_id,
                        'source_value' => $cell->source_value,
                        'value_kind' => $cell->value_kind,
                        'numeric_value' => $cell->numeric_value,
                        'apply_status' => 'applied',
                        'message' => null,
                        'student_result_id' => $result->id,
                        'applied_at' => $now,
                        'created_at' => $cell->created_at,
                        'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($revisionPayloads, 500) as $chunk) {
                    DB::table('lcd_record_revisions')->insertOrIgnore($chunk);
                }
            }
            foreach (array_chunk($cellUpdates, 500) as $chunk) {
                DB::table('annual_grade_import_cells')->upsert(
                    $chunk,
                    ['id'],
                    ['apply_status', 'message', 'student_result_id', 'applied_at', 'updated_at'],
                );
            }
        }

        $touched = $touchedAssessmentIds->unique()->values();
        if ($touched->isNotEmpty()) {
            DB::table('lcd_assessments')->whereIn('id', $touched)->whereNotIn('status', ['closed', 'amended', 'cancelled'])->update([
                'status' => 'results_open',
                'revision' => DB::raw('revision + 1'),
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_by' => $actor->id,
                'updated_at' => $now,
            ]);
        }
        $appliedRows = DB::table('annual_grade_import_cells')->where('annual_grade_import_id', $import->id)
            ->whereIn('apply_status', ['applied', 'unchanged', 'pending', 'preserved_manual'])
            ->pluck('annual_grade_import_row_id')->unique();
        foreach ($appliedRows->chunk(500) as $ids) {
            DB::table('annual_grade_import_rows')->whereIn('id', $ids)->update(['applied_at' => $now, 'updated_at' => $now]);
        }
    }

    private function refreshSummary(AnnualGradeImport $import): void
    {
        $matched = DB::table('annual_grade_import_rows')->where('annual_grade_import_id', $import->id)->whereNotNull('student_profile_id')->count();
        $unmatched = DB::table('annual_grade_import_rows')->where('annual_grade_import_id', $import->id)->whereNull('student_profile_id')->count();
        $applied = DB::table('annual_grade_import_cells')->where('annual_grade_import_id', $import->id)->whereIn('apply_status', ['applied', 'unchanged'])->count();
        $preserved = DB::table('annual_grade_import_cells')->where('annual_grade_import_id', $import->id)->whereIn('apply_status', ['preserved_manual', 'assessment_closed'])->count();
        $blocked = DB::table('annual_grade_import_cells')->where('annual_grade_import_id', $import->id)
            ->whereNotIn('apply_status', ['applied', 'unchanged', 'pending', 'preserved_manual', 'assessment_closed'])
            ->count();
        $status = ($unmatched > 0 || $blocked > 0 || (int) $import->invalid_cells > 0) ? 'partial' : 'completed';

        $import->forceFill([
            'status' => $status,
            'matched_rows' => $matched,
            'unmatched_rows' => $unmatched,
            'applied_results' => $applied,
            'preserved_manual_results' => $preserved,
            'blocked_cells' => $blocked,
            'completed_at' => now(),
        ])->save();
    }

    private function resolveSubject(
        string $sourceName,
        Collection $subjectMap,
        Collection $externalAliasMap,
        string $educationType,
    ): ?ScheduleSubject {
        $keys = [$this->parser->normalizeSubject($sourceName)];
        if (str_contains($sourceName, ':')) {
            $keys[] = $this->parser->normalizeSubject(Str::afterLast($sourceName, ':'));
        }
        foreach (array_unique($keys) as $key) {
            $mapped = collect([$educationType, 'all'])
                ->flatMap(fn (string $type) => $externalAliasMap->get($key.'|'.$type, collect()))
                ->pluck('subject')
                ->filter()
                ->unique('id')
                ->values();
            if ($mapped->count() === 1) {
                return $mapped->first();
            }
        }
        foreach ($keys as $key) {
            $key = self::SUBJECT_ALIASES[$key] ?? $key;
            $candidates = $subjectMap->get($key, collect());
            if ($candidates->count() === 1) {
                return $candidates->first();
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function blockedColumn(object $column, string $status, string $message, array $context = []): array
    {
        return [
            ...$this->columnBase($column),
            'course_section_id' => $context['course_section_id'] ?? null,
            'schedule_subject_id' => $context['schedule_subject_id'] ?? null,
            'book_id' => $context['book_id'] ?? null,
            'teaching_group_id' => $context['teaching_group_id'] ?? null,
            'teacher_assignment_id' => null,
            'assessment_id' => null,
            'import_key' => null,
            'mapping_status' => $status,
            'message' => $message,
            'updated_at' => now(),
        ];
    }

    /** @return array<string,mixed> */
    private function columnBase(object $column): array
    {
        return [
            'id' => $column->id,
            'annual_grade_import_id' => $column->annual_grade_import_id,
            'source_sheet' => $column->source_sheet,
            'source_column' => $column->source_column,
            'source_course_name' => $column->source_course_name,
            'normalized_course' => $column->normalized_course,
            'source_subject_name' => $column->source_subject_name,
            'normalized_subject' => $column->normalized_subject,
            'source_header' => $column->source_header,
            'normalized_header' => $column->normalized_header,
            'header_occurrence' => $column->header_occurrence,
            'subject_ordinal' => $column->subject_ordinal,
            'cell_count' => $column->cell_count,
            'numeric_count' => $column->numeric_count,
            'pending_count' => $column->pending_count,
            'not_applicable_count' => $column->not_applicable_count,
            'invalid_count' => $column->invalid_count,
            'created_at' => $column->created_at,
        ];
    }

    private function gradingScheme(School $school, AcademicYear $year): GradingScheme
    {
        return GradingScheme::query()->firstOrCreate(
            ['school_id' => $school->id, 'code' => 'CL_1_7', 'version' => '1'],
            [
                'name' => 'Escala chilena 1,0 a 7,0',
                'scale_type' => 'numeric',
                'minimum_value' => 1,
                'maximum_value' => 7,
                'passing_value' => 4,
                'decimal_places' => 1,
                'rounding_mode' => 'half_up',
                'rules' => ['input_contract' => '1_to_7'],
                'valid_from' => $year->starts_at,
                'valid_to' => $year->ends_at,
                'active' => true,
            ],
        );
    }

    /** @param Collection<int,int> $bookIds @return Collection<int,AssessmentPeriod> */
    private function assessmentPeriods(Collection $bookIds, School $school, AcademicYear $year): Collection
    {
        $existing = AssessmentPeriod::query()->whereIn('book_id', $bookIds)->where('code', 'ANNUAL')->get()->keyBy('book_id');
        $now = now();
        $missing = $bookIds->reject(fn (int $bookId): bool => $existing->has($bookId))->map(fn (int $bookId): array => [
            'public_id' => (string) Str::ulid(),
            'school_id' => $school->id,
            'book_id' => $bookId,
            'academic_year_id' => $year->id,
            'code' => 'ANNUAL',
            'name' => 'Año académico '.$year->year,
            'type' => 'academic_year',
            'starts_on' => $year->starts_at,
            'ends_on' => $year->ends_at,
            'weight' => 100,
            'status' => 'open',
            'revision' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();
        foreach (array_chunk($missing, 300) as $chunk) {
            DB::table('lcd_assessment_periods')->insertOrIgnore($chunk);
        }

        return AssessmentPeriod::query()->whereIn('book_id', $bookIds)->where('code', 'ANNUAL')->get()->keyBy('book_id');
    }

    /** @return Collection<int,array<string,mixed>> */
    private function candidatePayload(string $sourceName, ?string $sourceRut, Collection $enrollments): Collection
    {
        $normalizedSource = $this->parser->normalizeName($sourceName);

        return $enrollments->map(function (StudentEnrollment $enrollment) use ($normalizedSource, $sourceRut): array {
            $student = $enrollment->studentProfile;
            $name = $student?->registered_name ?: trim($student?->first_name.' '.$student?->last_name);
            similar_text($normalizedSource, $this->parser->normalizeName($name), $score);
            if ($sourceRut && $this->parser->normalizeRut((string) $student?->rut) === $sourceRut) {
                $score = 100;
            }

            return [
                'student_profile_id' => $enrollment->student_profile_id,
                'student_enrollment_id' => $enrollment->id,
                'name' => $name,
                'rut' => $student?->rut,
                'course' => $enrollment->courseSection?->display_name,
                'score' => round($score, 1),
            ];
        })->sortByDesc('score')->values();
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
