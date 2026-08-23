<?php

namespace App\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\MonthlyAttendanceImport;
use App\Models\Attendance\MonthlyAttendanceImportRow;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonthlyAttendanceImportService
{
    public function __construct(
        private readonly MonthlyAttendanceWorkbookParser $parser,
        private readonly AttendanceAlertService $alerts,
        private readonly AttendanceStatisticsCache $statisticsCache,
    ) {}

    public function import(UploadedFile $file, AcademicYear $academicYear, ?User $actor): MonthlyAttendanceImport
    {
        $parsed = $this->parser->parse($file->getRealPath());
        if ((int) $parsed['year'] !== (int) $academicYear->year) {
            throw ValidationException::withMessages([
                'file' => "El Excel corresponde a {$parsed['year']} y seleccionaste el año {$academicYear->year}.",
            ]);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        $existing = MonthlyAttendanceImport::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('month', $parsed['month'])
            ->where('checksum', $checksum)
            ->where('is_active', true)
            ->first();
        if ($existing) {
            return $existing;
        }

        $matchedRows = $this->matchRows($parsed['rows'], $academicYear);
        $disk = (string) config('attendance.imports_disk', 'local');
        $directory = trim((string) config('attendance.imports_path', 'attendance/imports'), '/').'/monthly-excel';
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'xls';
        $storedPath = $file->storeAs($directory, Str::uuid().'.'.$extension, $disk);

        try {
            $import = DB::transaction(function () use ($file, $academicYear, $parsed, $matchedRows, $checksum, $storedPath, $actor) {
                $previous = MonthlyAttendanceImport::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->where('month', $parsed['month'])
                    ->lockForUpdate()
                    ->get();
                $version = ((int) $previous->max('version')) + 1;

                $import = MonthlyAttendanceImport::query()->create([
                    'academic_year_id' => $academicYear->id,
                    'school_year' => $academicYear->year,
                    'month' => $parsed['month'],
                    'version' => $version,
                    'is_active' => true,
                    'status' => 'processing',
                    'source' => 'monthly_excel',
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize() ?: 0,
                    'checksum' => $checksum,
                    'sheet_count' => count($parsed['sheets']),
                    'parsed_rows' => count($matchedRows),
                    'metadata' => [
                        'period' => sprintf('%04d-%02d', $parsed['year'], $parsed['month']),
                        'month_label' => $parsed['month_label'],
                        'sheets' => $parsed['sheets'],
                        'source_summary' => $parsed['summary'],
                    ],
                    'created_by' => $actor?->id,
                ]);

                MonthlyAttendanceImport::query()
                    ->whereIn('id', $previous->where('is_active', true)->pluck('id'))
                    ->update([
                        'is_active' => false,
                        'status' => 'superseded',
                        'superseded_by_id' => $import->id,
                        'superseded_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->insertRows($import, $matchedRows);
                $rows = $import->rows()->whereNotNull('student_profile_id')->get();
                $writeResult = $this->applyMatchedRows($import, $rows, $actor);
                $matchedCount = $rows->count();
                $unmatchedCount = count($matchedRows) - $matchedCount;

                $import->update([
                    'status' => $unmatchedCount > 0 ? 'partial' : 'completed',
                    'matched_rows' => $matchedCount,
                    'unmatched_rows' => $unmatchedCount,
                    'imported_records' => $writeResult['written'],
                    'preserved_manual_records' => $writeResult['preserved_manual'],
                    'completed_at' => now(),
                ]);

                return $import;
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($storedPath);
            throw $exception;
        }

        $this->rebuildAlertsForImport($import);
        $this->statisticsCache->invalidate();

        return $import->refresh();
    }

    public function resolve(MonthlyAttendanceImportRow $row, int $studentProfileId, ?string $note, ?User $actor): MonthlyAttendanceImportRow
    {
        $row->loadMissing('monthlyImport');
        $import = $row->monthlyImport;
        if (! $import?->is_active) {
            throw ValidationException::withMessages([
                'row' => 'Este caso pertenece a una versión reemplazada y ya no se puede conciliar.',
            ]);
        }
        if ($row->student_profile_id) {
            throw ValidationException::withMessages([
                'row' => 'Este caso ya fue conciliado y no puede reasignarse desde la bandeja de pendientes.',
            ]);
        }

        $enrollment = StudentEnrollment::query()
            ->where('academic_year_id', $import->academic_year_id)
            ->where('student_profile_id', $studentProfileId)
            ->with(['studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name'])
            ->latest('id')
            ->first();
        if (! $enrollment) {
            throw ValidationException::withMessages([
                'student_profile_id' => 'La alumna seleccionada no tiene matrícula en el año del Excel.',
            ]);
        }

        $duplicate = MonthlyAttendanceImportRow::query()
            ->where('monthly_attendance_import_id', $import->id)
            ->where('student_profile_id', $studentProfileId)
            ->whereKeyNot($row->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'student_profile_id' => 'La alumna ya está asociada a otra fila de este mismo Excel.',
            ]);
        }

        DB::transaction(function () use ($row, $enrollment, $note, $actor, $import): void {
            $row->update([
                'student_profile_id' => $enrollment->student_profile_id,
                'student_enrollment_id' => $enrollment->id,
                'course_section_id' => $enrollment->course_section_id,
                'match_status' => 'manual',
                'match_confidence' => 100,
                'resolution_note' => $note,
                'matched_by' => $actor?->id,
                'matched_at' => now(),
            ]);

            $writeResult = $this->applyMatchedRows($import, collect([$row->fresh()]), $actor);
            $unmatched = $import->rows()->whereNull('student_profile_id')->count();
            $matched = $import->parsed_rows - $unmatched;
            $import->update([
                'status' => $unmatched > 0 ? 'partial' : 'completed',
                'matched_rows' => $matched,
                'unmatched_rows' => $unmatched,
                'imported_records' => $import->imported_records + $writeResult['written'],
                'preserved_manual_records' => $import->preserved_manual_records + $writeResult['preserved_manual'],
            ]);
        });

        if ($row->course_section_id) {
            $this->alerts->rebuild($import->academic_year_id, $row->course_section_id);
        }
        $this->statisticsCache->invalidate();

        return $row->fresh(['student:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name', 'matchedBy:id,name']);
    }

    /** @param array<int,array<string,mixed>> $sourceRows */
    private function matchRows(array $sourceRows, AcademicYear $academicYear): array
    {
        $enrollments = StudentEnrollment::query()
            ->where('academic_year_id', $academicYear->id)
            ->with([
                'studentProfile:id,first_name,last_name,registered_name,rut',
                'courseSection:id,display_name',
            ])
            ->orderByDesc('id')
            ->get()
            ->unique('student_profile_id')
            ->values();
        $courses = CourseSection::query()
            ->where('academic_year_id', $academicYear->id)
            ->get(['id', 'display_name'])
            ->keyBy(fn (CourseSection $course): string => $this->normalizeCourse($course->display_name));
        $byRut = $enrollments->filter(fn (StudentEnrollment $enrollment): bool => filled($enrollment->studentProfile?->rut))
            ->keyBy(fn (StudentEnrollment $enrollment): string => $this->normalizeRut($enrollment->studentProfile->rut));

        $matchedRows = array_map(function (array $row) use ($enrollments, $courses, $byRut): array {
            $sourceCourse = $courses->get($this->normalizeCourse((string) $row['source_course_name']));
            $rutEnrollment = $byRut->get((string) $row['normalized_rut']);
            $candidatePool = $sourceCourse
                ? $enrollments->where('course_section_id', $sourceCourse->id)
                : $enrollments;
            $candidates = $this->candidatePayload($row, $candidatePool, $rutEnrollment);

            if ($rutEnrollment && (! $sourceCourse || (int) $rutEnrollment->course_section_id === (int) $sourceCourse->id)) {
                return [...$row,
                    'student_profile_id' => $rutEnrollment->student_profile_id,
                    'student_enrollment_id' => $rutEnrollment->id,
                    'course_section_id' => $rutEnrollment->course_section_id,
                    'match_status' => 'exact_rut',
                    'match_confidence' => 100,
                    'candidates' => $candidates,
                ];
            }

            return [...$row,
                'student_profile_id' => null,
                'student_enrollment_id' => null,
                'course_section_id' => $sourceCourse?->id,
                'match_status' => $rutEnrollment ? 'course_conflict' : ($sourceCourse ? 'unmatched' : 'course_not_found'),
                'match_confidence' => $rutEnrollment ? 100 : ($candidates[0]['score'] ?? null),
                'candidates' => $candidates,
            ];
        }, $sourceRows);

        $matchedStudentIds = collect($matchedRows)
            ->pluck('student_profile_id')
            ->filter()
            ->mapWithKeys(fn ($studentProfileId): array => [(int) $studentProfileId => true]);

        return array_map(function (array $row) use ($matchedStudentIds): array {
            if ($row['student_profile_id']) {
                return $row;
            }

            $row['candidates'] = collect($row['candidates'])
                ->reject(fn (array $candidate): bool => $matchedStudentIds->has((int) $candidate['student_profile_id']))
                ->values()
                ->all();
            $row['match_confidence'] = $row['match_status'] === 'course_conflict'
                ? $row['match_confidence']
                : ($row['candidates'][0]['score'] ?? null);

            return $row;
        }, $matchedRows);
    }

    /** @return array<int,array<string,mixed>> */
    private function candidatePayload(array $row, Collection $pool, ?StudentEnrollment $rutEnrollment): array
    {
        $sourceName = $this->normalizeName((string) $row['source_name']);
        $scored = $pool->map(function (StudentEnrollment $enrollment) use ($sourceName, $row): array {
            $candidateName = $enrollment->studentProfile?->registered_name ?: trim($enrollment->studentProfile?->first_name.' '.$enrollment->studentProfile?->last_name);
            similar_text($sourceName, $this->normalizeName($candidateName), $score);
            if ($this->normalizeRut((string) $enrollment->studentProfile?->rut) === (string) $row['normalized_rut']) {
                $score = 100;
            }

            return [
                'student_profile_id' => $enrollment->student_profile_id,
                'student_enrollment_id' => $enrollment->id,
                'name' => $candidateName,
                'rut' => $enrollment->studentProfile?->rut,
                'course' => $enrollment->courseSection?->display_name,
                'score' => round($score, 1),
            ];
        })->sortByDesc('score')->take(5)->values();

        if ($rutEnrollment && ! $scored->contains('student_profile_id', $rutEnrollment->student_profile_id)) {
            $scored->prepend([
                'student_profile_id' => $rutEnrollment->student_profile_id,
                'student_enrollment_id' => $rutEnrollment->id,
                'name' => $rutEnrollment->studentProfile?->registered_name ?: trim($rutEnrollment->studentProfile?->first_name.' '.$rutEnrollment->studentProfile?->last_name),
                'rut' => $rutEnrollment->studentProfile?->rut,
                'course' => $rutEnrollment->courseSection?->display_name,
                'score' => 100.0,
            ]);
        }

        return $scored->take(5)->values()->all();
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function insertRows(MonthlyAttendanceImport $import, array $rows): void
    {
        $now = now();
        $payloads = array_map(static fn (array $row): array => [
            'monthly_attendance_import_id' => $import->id,
            'student_profile_id' => $row['student_profile_id'],
            'student_enrollment_id' => $row['student_enrollment_id'],
            'course_section_id' => $row['course_section_id'],
            'source_sheet' => $row['source_sheet'],
            'source_row' => $row['source_row'],
            'source_course_name' => $row['source_course_name'],
            'list_number' => $row['list_number'],
            'given_names' => $row['given_names'],
            'paternal_surname' => $row['paternal_surname'],
            'maternal_surname' => $row['maternal_surname'],
            'source_name' => $row['source_name'],
            'source_rut' => $row['source_rut'],
            'normalized_rut' => $row['normalized_rut'],
            'present_days' => $row['present_days'],
            'absent_days' => $row['absent_days'],
            'class_days' => $row['class_days'],
            'attendance_rate' => $row['attendance_rate'],
            'is_sep_priority' => $row['is_sep_priority'],
            'is_sep_preferential' => $row['is_sep_preferential'],
            'is_pie' => $row['is_pie'],
            'daily_records' => json_encode($row['daily_records'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'match_status' => $row['match_status'],
            'match_confidence' => $row['match_confidence'],
            'candidates' => json_encode($row['candidates'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'matched_at' => $row['student_profile_id'] ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        foreach (array_chunk($payloads, 400) as $chunk) {
            DB::table('monthly_attendance_import_rows')->insert($chunk);
        }
    }

    /**
     * @param  Collection<int,MonthlyAttendanceImportRow>  $rows
     * @return array{written:int,preserved_manual:int}
     */
    private function applyMatchedRows(MonthlyAttendanceImport $import, Collection $rows, ?User $actor): array
    {
        if ($rows->isEmpty()) {
            return ['written' => 0, 'preserved_manual' => 0];
        }

        $now = now();
        $dates = $rows->flatMap(fn (MonthlyAttendanceImportRow $row): array => array_column($row->daily_records ?? [], 'date'))->unique()->values();
        $dayPayloads = $dates->map(fn (string $date): array => [
            'academic_year_id' => $import->academic_year_id,
            'date' => $date,
            'is_school_day' => true,
            'status' => 'confirmed',
            'source' => 'monthly_excel',
            'metadata' => json_encode(['monthly_attendance_import_id' => $import->id]),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        foreach (array_chunk($dayPayloads, 400) as $chunk) {
            DB::table('school_days')->insertOrIgnore($chunk);
        }
        $schoolDays = DB::table('school_days')
            ->where('academic_year_id', $import->academic_year_id)
            ->whereIn('date', $dates)
            ->pluck('id', 'date');

        $studentIds = $rows->pluck('student_profile_id')->filter()->unique()->values();
        $existing = AttendanceRecord::query()
            ->where('academic_year_id', $import->academic_year_id)
            ->whereIn('student_profile_id', $studentIds)
            ->whereIn('attendance_date', $dates)
            ->get(['id', 'course_section_id', 'student_profile_id', 'attendance_date', 'origin'])
            ->keyBy(fn (AttendanceRecord $record): string => $record->course_section_id.'|'.$record->student_profile_id.'|'.$record->attendance_date->format('Y-m-d'));

        $records = [];
        $preservedManual = 0;
        foreach ($rows as $row) {
            foreach ($row->daily_records ?? [] as $record) {
                $key = $row->course_section_id.'|'.$row->student_profile_id.'|'.$record['date'];
                if ($existing->get($key)?->origin === 'manual') {
                    $preservedManual++;

                    continue;
                }
                $records[] = [
                    'monthly_attendance_import_id' => $import->id,
                    'monthly_attendance_import_row_id' => $row->id,
                    'school_day_id' => $schoolDays[$record['date']],
                    'academic_year_id' => $import->academic_year_id,
                    'course_section_id' => $row->course_section_id,
                    'student_profile_id' => $row->student_profile_id,
                    'student_enrollment_id' => $row->student_enrollment_id,
                    'attendance_date' => $record['date'],
                    'status' => $record['status'],
                    'origin' => 'monthly_excel',
                    'source_symbol' => $record['symbol'],
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($records, 700) as $chunk) {
            AttendanceRecord::query()->upsert(
                $chunk,
                ['course_section_id', 'student_profile_id', 'attendance_date'],
                [
                    'monthly_attendance_import_id', 'monthly_attendance_import_row_id', 'school_day_id',
                    'academic_year_id', 'student_enrollment_id', 'status', 'origin', 'source_symbol',
                    'updated_by', 'updated_at',
                ],
            );
        }

        MonthlyAttendanceImportRow::query()->whereIn('id', $rows->pluck('id'))->update(['applied_at' => $now]);
        $this->syncSupportFlags($import, $rows, $actor);

        return ['written' => count($records), 'preserved_manual' => $preservedManual];
    }

    /** @param Collection<int,MonthlyAttendanceImportRow> $rows */
    private function syncSupportFlags(MonthlyAttendanceImport $import, Collection $rows, ?User $actor): void
    {
        $newerMonthExists = MonthlyAttendanceImport::query()
            ->where('academic_year_id', $import->academic_year_id)
            ->where('is_active', true)
            ->where('month', '>', $import->month)
            ->exists();
        if ($newerMonthExists) {
            return;
        }

        $latestRows = $rows->sortByDesc('id')->unique('student_profile_id')->values();
        // El Excel confirma inclusiones, pero nunca revoca una condición PIE registrada por otra fuente.
        $pieIds = $latestRows->where('is_pie', true)->pluck('student_profile_id')->filter();
        if ($pieIds->isNotEmpty()) {
            StudentProfile::query()
                ->whereIn('id', $pieIds)
                ->where(fn ($query) => $query->where('is_pie_participant', false)->orWhereNull('is_pie_participant'))
                ->update([
                    'is_pie_participant' => true,
                    'updated_by' => $actor?->id,
                    'updated_at' => now(),
                ]);
        }

        $now = now();
        $existingSepSources = DB::table('pme_estudiantes_sep')
            ->where('academic_year_id', $import->academic_year_id)
            ->whereIn('student_profile_id', $latestRows->pluck('student_profile_id'))
            ->pluck('source', 'student_profile_id');
        $sepPayloads = $latestRows
            ->filter(fn (MonthlyAttendanceImportRow $row): bool => (
                ! $existingSepSources->has($row->student_profile_id)
                    && ($row->is_sep_priority || $row->is_sep_preferential)
            ) || $existingSepSources->get($row->student_profile_id) === 'Excel asistencia mensual')
            ->map(function (MonthlyAttendanceImportRow $row) use ($import, $actor, $now): array {
                $classification = $row->is_sep_priority
                    ? 'prioritaria'
                    : ($row->is_sep_preferential ? 'preferente' : 'sin_clasificacion_sep');

                return [
                    'student_profile_id' => $row->student_profile_id,
                    'course_section_id' => $row->course_section_id,
                    'academic_year_id' => $import->academic_year_id,
                    'classification' => $classification,
                    'loaded_at' => $now->toDateString(),
                    'source' => 'Excel asistencia mensual',
                    'supporting_document_name' => $import->original_filename,
                    'state' => 'vigente',
                    'observations' => sprintf('Clasificación informada en %02d/%d.', $import->month, $import->school_year),
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            })->all();

        foreach (array_chunk($sepPayloads, 400) as $chunk) {
            DB::table('pme_estudiantes_sep')->upsert(
                $chunk,
                ['student_profile_id', 'academic_year_id'],
                [
                    'course_section_id', 'classification', 'loaded_at', 'source',
                    'supporting_document_name', 'state', 'observations', 'updated_by', 'updated_at', 'deleted_at',
                ],
            );
        }
    }

    private function rebuildAlertsForImport(MonthlyAttendanceImport $import): void
    {
        $courseIds = $import->rows()->whereNotNull('course_section_id')->distinct()->pluck('course_section_id');
        foreach ($courseIds as $courseId) {
            $this->alerts->rebuild($import->academic_year_id, (int) $courseId);
        }
    }

    private function normalizeRut(string $rut): string
    {
        return ltrim(strtoupper(preg_replace('/[^0-9Kk]/', '', $rut) ?? ''), '0');
    }

    private function normalizeCourse(string $course): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($course))) ?? '';
    }

    private function normalizeName(string $name): string
    {
        $tokens = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($tokens);

        return implode(' ', $tokens);
    }
}
