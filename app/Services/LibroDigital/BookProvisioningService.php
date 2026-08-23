<?php

namespace App\Services\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EnrollmentLink;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\RosterSnapshot;
use App\Models\LibroDigital\RosterSnapshotItem;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\TeacherAssignment;
use App\Models\LibroDigital\TeachingGroup;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookProvisioningService
{
    public function __construct(private readonly CanonicalJson $canonical) {}

    /**
     * Creates the immutable initial book structure and sealed roster. A teacher
     * may be omitted by governed preparation processes. A later administrative
     * bulk opening may accept that warning explicitly; operational records and
     * signatures continue requiring a valid teacher assignment.
     *
     * @param  array{name?: ?string, modality?: ?string, notes?: ?string}  $options
     */
    public function provision(
        School $school,
        AcademicYear $year,
        CourseSection $course,
        ScheduleSubject $subject,
        RegulatoryProfile $profile,
        User $actor,
        ?Staff $teacher,
        array $options = [],
    ): Book {
        return DB::transaction(function () use ($school, $year, $course, $subject, $profile, $actor, $teacher, $options): Book {
            $existing = Book::query()
                ->where('school_id', $school->id)
                ->where('academic_year_id', $year->id)
                ->where('course_section_id', $course->id)
                ->where('status', '<>', 'archived')
                ->whereHas('teachingGroups', fn ($query) => $query->where('schedule_subject_id', $subject->id))
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $codeBase = Str::upper('LCD-'.$year->year.'-'.$course->id.'-'.$subject->id);
            $code = $codeBase;
            $suffix = 1;
            while (Book::query()->where('school_id', $school->id)->where('academic_year_id', $year->id)->where('code', $code)->exists()) {
                $code = $codeBase.'-'.(++$suffix);
            }

            $book = Book::query()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'regulatory_profile_id' => $profile->id,
                'course_section_id' => $course->id,
                'code' => $code,
                'rbd_snapshot' => $school->rbd,
                'year_snapshot' => $year->year,
                'level_code' => $course->educationLevel?->type,
                'grade_code' => $course->educationLevel?->name,
                'course_label' => $course->display_name,
                'modality_code' => $options['modality'] ?? $course->educationLevel?->type,
                'status' => 'draft',
                'revision' => 1,
                'lock_version' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $effectiveOn = max($year->starts_at?->toDateString() ?? now()->toDateString(), now()->toDateString());
            $group = TeachingGroup::query()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'book_id' => $book->id,
                'course_section_id' => $course->id,
                'schedule_subject_id' => $subject->id,
                'code' => $code.'-G1',
                'name' => $options['name'] ?? $course->display_name.' · '.$subject->resolvedDisplayName(),
                'course_snapshot' => $course->display_name,
                'subject_snapshot' => $subject->resolvedDisplayName(),
                'valid_from' => $year->starts_at ?? now()->toDateString(),
                'valid_to' => $year->ends_at,
                'metadata' => ['notes' => $options['notes'] ?? null],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($teacher) {
                $teacher->loadMissing('user');
                TeacherAssignment::query()->create([
                    'school_id' => $school->id,
                    'academic_year_id' => $year->id,
                    'book_id' => $book->id,
                    'teaching_group_id' => $group->id,
                    'staff_id' => $teacher->id,
                    'user_id' => $teacher->user?->id,
                    'schedule_subject_id' => $subject->id,
                    'teacher_name_snapshot' => $teacher->full_name,
                    'valid_from' => $year->starts_at ?? now()->toDateString(),
                    'valid_to' => $year->ends_at,
                    'is_primary' => true,
                    'active' => true,
                    'assigned_by' => $actor->id,
                ]);
            }

            $enrollments = StudentEnrollment::query()->with('studentProfile')
                ->where('academic_year_id', $year->id)
                ->where('course_section_id', $course->id)
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
                ->orderBy('id')
                ->get();
            $snapshotRows = [];
            foreach ($enrollments as $index => $enrollment) {
                $student = $enrollment->studentProfile;
                $link = EnrollmentLink::query()->create([
                    'school_id' => $school->id,
                    'book_id' => $book->id,
                    'teaching_group_id' => $group->id,
                    'student_profile_id' => $student->id,
                    'student_enrollment_id' => $enrollment->id,
                    'course_section_id' => $course->id,
                    'list_number' => $index + 1,
                    'effective_from' => $enrollment->enrolled_at ?? $effectiveOn,
                    'effective_to' => $enrollment->withdrawn_at,
                    'status' => 'active',
                    'student_name_snapshot' => $student->registered_name_resolved,
                    'identifier_snapshot_encrypted' => filled($student->rut) ? Crypt::encryptString((string) $student->rut) : null,
                    'enrollment_status_snapshot' => $enrollment->enrollment_status,
                    'course_snapshot' => $course->display_name,
                    'created_by' => $actor->id,
                ]);
                $snapshotRows[] = compact('link', 'student', 'enrollment') + ['list_number' => $index + 1];
            }

            $snapshotPayload = collect($snapshotRows)->map(fn (array $row) => [
                'student_profile_id' => $row['student']->id,
                'student_enrollment_id' => $row['enrollment']->id,
                'list_number' => $row['list_number'],
                'name' => $row['student']->registered_name_resolved,
                'status' => $row['enrollment']->enrollment_status,
            ])->all();
            $snapshot = RosterSnapshot::query()->create([
                'book_id' => $book->id,
                'teaching_group_id' => $group->id,
                'effective_on' => $effectiveOn,
                'reason' => 'book_created',
                'status' => 'sealed',
                'student_count' => count($snapshotRows),
                'snapshot_hash' => $this->canonical->hash($snapshotPayload),
                'created_by' => $actor->id,
            ]);
            foreach ($snapshotRows as $row) {
                $payload = [
                    'roster_snapshot_id' => $snapshot->id,
                    'enrollment_link_id' => $row['link']->id,
                    'student_profile_id' => $row['student']->id,
                    'student_enrollment_id' => $row['enrollment']->id,
                    'list_number' => $row['list_number'],
                    'active_from' => $row['link']->effective_from,
                    'active_to' => $row['link']->effective_to,
                    'applicability_status' => 'applicable',
                    'student_name_snapshot' => $row['student']->registered_name_resolved,
                    'identifier_snapshot_encrypted' => $row['link']->identifier_snapshot_encrypted,
                    'course_snapshot' => $course->display_name,
                    'enrollment_status_snapshot' => $row['enrollment']->enrollment_status,
                ];
                RosterSnapshotItem::query()->create($payload + ['record_hash' => $this->canonical->hash($payload)]);
            }

            return $book;
        }, 3);
    }
}
