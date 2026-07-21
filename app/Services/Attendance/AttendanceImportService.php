<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceImport;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\SchoolDay;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceImportService
{
    public function __construct(
        private readonly AttendanceParserRegistry $parsers,
        private readonly AttendanceStudentMatcher $matcher,
        private readonly AttendanceAlertService $alerts,
        private readonly AttendanceStatisticsCache $statisticsCache,
    ) {}

    public function preview(UploadedFile $file, CourseSection $course, ?User $user): AttendanceImport
    {
        $course->loadMissing('academicYear', 'educationLevel');
        $checksum = hash_file('sha256', $file->getRealPath());
        $existing = AttendanceImport::query()
            ->where('course_section_id', $course->id)
            ->where('checksum', $checksum)
            ->first();

        if ($existing) {
            return $existing->load('courseSection:id,display_name', 'academicYear:id,name,year');
        }

        $disk = (string) config('attendance.imports_disk', 'local');
        $directory = trim((string) config('attendance.imports_path', 'attendance/imports'), '/');
        $storedPath = $file->storeAs($directory, Str::uuid().'.pdf', $disk);

        try {
            $parsed = $this->parsers->parse(Storage::disk($disk)->path($storedPath));
            $this->validatePeriod($parsed, $course);
            $matchedStudents = $this->matcher->match($parsed['students'], $course);
            $parsed['students'] = $matchedStudents;
            $matched = count(array_filter($matchedStudents, static fn (array $row) => $row['matched_student_id'] !== null));
            $unmatched = count($matchedStudents) - $matched;
            $warnings = $this->previewWarnings($parsed, $course, $unmatched);

            $import = AttendanceImport::query()->create([
                'academic_year_id' => $course->academic_year_id,
                'course_section_id' => $course->id,
                'source' => $parsed['document']['source'],
                'status' => 'preview',
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'checksum' => $checksum,
                'parsed_students' => count($matchedStudents),
                'matched_students' => $matched,
                'unmatched_students' => $unmatched,
                'preview_payload' => $parsed,
                'validation_payload' => [
                    'checks' => $parsed['validation'],
                    'warnings' => $warnings,
                ],
                'created_by' => $user?->id,
            ]);

            return $import->load('courseSection:id,display_name', 'academicYear:id,name,year');
        } catch (\RuntimeException $exception) {
            Storage::disk($disk)->delete($storedPath);
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($storedPath);
            throw $exception;
        }
    }

    public function confirm(AttendanceImport $import, array $input, ?User $user): AttendanceImport
    {
        if ($import->status === 'completed') {
            return $import->load('courseSection:id,display_name', 'academicYear:id,name,year');
        }

        if ($import->status !== 'preview' || ! is_array($import->preview_payload)) {
            throw ValidationException::withMessages(['import' => 'La importación ya no está disponible para confirmar.']);
        }

        $strategy = $input['conflict_strategy'] ?? 'reject';
        $students = $this->applyOverrides(
            $import,
            $import->preview_payload['students'],
            $input['student_matches'] ?? [],
        );
        $unmatched = array_values(array_filter($students, static fn (array $row) => empty($row['matched_student_id'])));
        if ($unmatched !== []) {
            throw ValidationException::withMessages([
                'student_matches' => 'Debes asociar todas las filas antes de confirmar. Pendientes: '.implode(', ', array_column($unmatched, 'name')),
            ]);
        }

        $result = DB::transaction(function () use ($import, $students, $strategy, $user) {
            $dayModels = [];
            foreach ($import->preview_payload['days'] as $day) {
                $schoolDay = SchoolDay::query()->firstOrCreate(
                    ['academic_year_id' => $import->academic_year_id, 'date' => $day['date']],
                    [
                        'is_school_day' => true,
                        'status' => $day['confirmation_status'],
                        'source' => 'attendance_import',
                        'metadata' => ['attendance_import_id' => $import->id],
                        'created_by' => $user?->id,
                        'updated_by' => $user?->id,
                    ],
                );
                if ($day['is_anomaly'] && $schoolDay->status !== 'pending_confirmation') {
                    $schoolDay->update([
                        'status' => 'pending_confirmation',
                        'metadata' => ['attendance_import_id' => $import->id, 'anomaly' => true],
                        'updated_by' => $user?->id,
                    ]);
                }
                $dayModels[$day['date']] = $schoolDay;
            }

            $studentIds = array_column($students, 'matched_student_id');
            $dates = array_column($import->preview_payload['days'], 'date');
            $existing = AttendanceRecord::query()
                ->where('course_section_id', $import->course_section_id)
                ->whereIn('student_profile_id', $studentIds)
                ->whereIn('attendance_date', $dates)
                ->get()
                ->keyBy(fn (AttendanceRecord $record) => $record->student_profile_id.'|'.$record->attendance_date->format('Y-m-d'));

            $conflicts = 0;
            foreach ($students as $student) {
                foreach ($student['records'] as $record) {
                    $current = $existing->get($student['matched_student_id'].'|'.$record['date']);
                    if ($current && $current->status !== $record['status']) {
                        $conflicts++;
                    }
                }
            }

            if ($strategy === 'reject' && $conflicts > 0) {
                throw ValidationException::withMessages([
                    'conflict_strategy' => "La importación tiene {$conflicts} conflictos. Selecciona sobrescribir o conservar existentes.",
                ]);
            }

            $written = 0;
            foreach ($students as $student) {
                foreach ($student['records'] as $record) {
                    $key = $student['matched_student_id'].'|'.$record['date'];
                    $current = $existing->get($key);
                    if ($current && $current->status !== $record['status'] && $strategy === 'skip') {
                        continue;
                    }
                    if ($current && $current->status === $record['status']) {
                        continue;
                    }

                    $payload = [
                        'attendance_import_id' => $import->id,
                        'school_day_id' => $dayModels[$record['date']]->id,
                        'academic_year_id' => $import->academic_year_id,
                        'course_section_id' => $import->course_section_id,
                        'student_profile_id' => $student['matched_student_id'],
                        'student_enrollment_id' => $student['matched_enrollment_id'],
                        'attendance_date' => $record['date'],
                        'status' => $record['status'],
                        'origin' => 'import',
                        'source_symbol' => $record['symbol'],
                        'updated_by' => $user?->id,
                    ];

                    if ($current) {
                        $current->update($payload);
                    } else {
                        AttendanceRecord::query()->create([...$payload, 'created_by' => $user?->id]);
                    }
                    $written++;
                }
            }

            $import->update([
                'status' => 'completed',
                'conflict_strategy' => $strategy,
                'matched_students' => count($students),
                'unmatched_students' => 0,
                'imported_records' => $written,
                'conflict_records' => $conflicts,
                'confirmed_at' => now(),
                'confirmed_by' => $user?->id,
            ]);

            return $import;
        });

        $this->alerts->rebuild($import->academic_year_id, $import->course_section_id);
        $this->statisticsCache->invalidate();

        return $result->fresh()->load('courseSection:id,display_name', 'academicYear:id,name,year');
    }

    private function applyOverrides(AttendanceImport $import, array $students, array $overrides): array
    {
        $roster = StudentEnrollment::query()
            ->where('academic_year_id', $import->academic_year_id)
            ->where('course_section_id', $import->course_section_id)
            ->get()
            ->keyBy('student_profile_id');
        $overrides = collect($overrides)->keyBy('row');

        return array_map(function (array $student) use ($overrides, $roster) {
            $override = $overrides->get($student['row']);
            if (! $override || empty($override['student_profile_id'])) {
                return $student;
            }

            $enrollment = $roster->get((int) $override['student_profile_id']);
            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'student_matches' => "La asociación de la fila {$student['row']} no pertenece al curso seleccionado.",
                ]);
            }

            $student['matched_student_id'] = $enrollment->student_profile_id;
            $student['matched_enrollment_id'] = $enrollment->id;
            $student['match_status'] = 'manual';

            return $student;
        }, $students);
    }

    private function validatePeriod(array $parsed, CourseSection $course): void
    {
        if ((int) $parsed['document']['year'] !== (int) $course->academicYear->year) {
            throw ValidationException::withMessages([
                'file' => "El PDF corresponde a {$parsed['document']['year']} y el curso seleccionado a {$course->academicYear->year}.",
            ]);
        }
    }

    private function previewWarnings(array $parsed, CourseSection $course, int $unmatched): array
    {
        $warnings = [];
        similar_text($this->normalizeCourse($parsed['document']['course_name']), $this->normalizeCourse($course->display_name), $score);
        if ($score < 88) {
            $warnings[] = [
                'code' => 'course_mismatch',
                'level' => 'warning',
                'message' => "El PDF indica {$parsed['document']['course_name']} y seleccionaste {$course->display_name}.",
            ];
        }
        if ($unmatched > 0) {
            $warnings[] = [
                'code' => 'unmatched_students',
                'level' => 'warning',
                'message' => "Hay {$unmatched} filas sin una coincidencia única en el curso.",
            ];
        }
        if (($parsed['summary']['anomaly_days'] ?? 0) > 0) {
            $warnings[] = [
                'code' => 'anomaly_days',
                'level' => 'warning',
                'message' => 'Se detectó al menos un día con 0% de asistencia. Se importará pendiente de confirmación.',
            ];
        }

        return $warnings;
    }

    private function normalizeCourse(string $value): string
    {
        return Str::lower(preg_replace('/[^a-z0-9]+/', '', Str::ascii($value)));
    }
}
