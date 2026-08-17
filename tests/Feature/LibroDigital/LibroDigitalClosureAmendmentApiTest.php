<?php

namespace Tests\Feature\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\RosterSnapshot;
use App\Models\LibroDigital\RosterSnapshotItem;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\SessionAttendance;
use App\Models\LibroDigital\TeachingGroup;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\LibroDigital\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibroDigitalClosureAmendmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_monthly_closures_and_manual_reconciliation_use_sealed_public_references(): void
    {
        [$requester, , , $school, , , , , $book, $group, $snapshot, $item, $session] = $this->context();
        config(['libro_digital.sige.driver' => 'manual']);

        $catalogs = $this->actingAs($requester)->getJson('/api/libro-digital/v1/catalogs?school_id='.$school->id)
            ->assertOk()
            ->assertJsonPath('data.capabilities.can_manage_closures', true)
            ->assertJsonPath('data.capabilities.can_reconcile_attendance', true)
            ->assertJsonPath('data.capabilities.can_request_amendments', true);
        $this->assertTrue($catalogs->json('data.capabilities.can_review_amendments'));
        $this->assertTrue($catalogs->json('data.capabilities.can_apply_amendments'));

        $bookResponse = $this->getJson('/api/libro-digital/v1/books/'.$book->id)
            ->assertOk()->assertJsonPath('data.teaching_group_public_id', $group->public_id);
        $this->assertSame($group->public_id, $bookResponse->json('data.teaching_group_public_id'));
        $roster = $this->getJson('/api/libro-digital/v1/books/'.$book->id.'/roster?date='.$session->session_date->format('Y-m-d'))
            ->assertOk()
            ->assertJsonPath('data.0.roster_snapshot_item_id', $item->id)
            ->assertJsonPath('data.0.student_reference', $item->enrollmentLink->public_id)
            ->assertJsonPath('meta.snapshot.teaching_group_public_id', $group->public_id);
        $this->assertSame($item->enrollmentLink->public_id, $roster->json('data.0.enrollment_link_public_id'));

        $daily = $this->withHeaders(['Idempotency-Key' => 'lcd-daily-close-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/daily-close', [
                'date' => $session->session_date->format('Y-m-d'),
                'teaching_group_id' => $group->id,
                'lock_version' => 1,
            ])->assertCreated()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_count', 1)
            ->assertJsonPath('data.present_count', 1)
            ->assertJsonPath('data.revision', 1);

        $month = (int) $session->session_date->month;
        $monthly = $this->withHeaders(['Idempotency-Key' => 'lcd-monthly-close-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/monthly-close', [
                'month' => $month,
                'teaching_group_id' => $group->id,
                'lock_version' => 1,
            ])->assertCreated()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_total', 1)
            ->assertJsonPath('data.present_total', 1)
            ->assertJsonPath('data.revision', 1);

        $this->withHeaders(['Idempotency-Key' => 'lcd-daily-close-duplicate-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/daily-close', [
                'date' => $session->session_date->format('Y-m-d'),
                'teaching_group_id' => $group->id,
                'lock_version' => 1,
            ])->assertStatus(409)->assertJsonPath('code', 'LCD_DAILY_ALREADY_CLOSED');

        $reconciliation = [
            'month' => $month,
            'evidence_reference' => 'SIGE-EVIDENCE-0001',
            'rows' => [['student_reference' => $item->enrollmentLink->public_id]],
            'groups' => [[
                'teaching_group_public_id' => $group->public_id,
                'expected_total' => 1,
                'present_total' => 1,
                'absent_total' => 0,
            ]],
            'lock_version' => 1,
        ];
        $this->withHeaders(['Idempotency-Key' => 'lcd-reconcile-disabled-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/reconcile', $reconciliation)
            ->assertStatus(503)->assertJsonPath('code', 'LCD_SIGE_RECONCILIATION_FEATURE_DISABLED');
        FeatureFlag::query()->create([
            'school_id' => $school->id,
            'scope_key' => 'school:'.$school->id,
            'code' => 'lcd_sige_reconciliation_enabled',
            'enabled' => true,
        ]);
        $this->withHeaders(['Idempotency-Key' => 'lcd-reconcile-manual-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/reconcile', $reconciliation)
            ->assertCreated()
            ->assertJsonPath('data.official', false)
            ->assertJsonPath('data.official_submission_performed', false)
            ->assertJsonPath('data.differences', []);

        $this->assertDatabaseHas('lcd_daily_attendance_closures', ['id' => $daily->json('data.id'), 'revision' => 1, 'status' => 'closed']);
        $this->assertDatabaseHas('lcd_monthly_attendance_closures', ['id' => $monthly->json('data.id'), 'revision' => 1, 'status' => 'closed']);
        $this->assertDatabaseHas('lcd_attendance_reconciliations', ['book_id' => $book->id, 'month' => $month, 'external_source' => 'sige_manual_evidence']);
        $this->assertDatabaseCount('lcd_daily_attendance_closures', 1);
    }

    public function test_approved_amendment_requires_three_people_and_reopens_then_versions_derived_closures(): void
    {
        [$requester, $reviewer, $applier, , , , , , $book, $group, , , $session] = $this->context();
        $date = $session->session_date->format('Y-m-d');
        $month = (int) $session->session_date->month;

        $this->actingAs($requester)->withHeaders(['Idempotency-Key' => 'lcd-amend-daily-close-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/daily-close', [
                'date' => $date,
                'teaching_group_id' => $group->id,
                'lock_version' => 1,
            ])->assertCreated();
        $this->withHeaders(['Idempotency-Key' => 'lcd-amend-month-close-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/monthly-close', [
                'month' => $month,
                'teaching_group_id' => $group->id,
                'lock_version' => 1,
            ])->assertCreated();

        $created = $this->withHeader('Idempotency-Key', 'lcd-amend-request-0001')
            ->postJson('/api/libro-digital/v1/amendments', [
                'book_id' => $book->id,
                'entity_type' => 'session',
                'entity_id' => $session->id,
                'original_revision' => 1,
                'section' => 'lesson_record',
                'field' => 'observation',
                'proposed' => ['observation' => 'Corrección formal con evidencia revisada.'],
                'reason' => 'Se detectó un error de transcripción en el registro firmado.',
            ])->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.proposed.observation', 'Corrección formal con evidencia revisada.');
        $amendmentId = $created->json('data.id');
        $this->assertNull($session->fresh()->observation);

        $this->withHeader('Idempotency-Key', 'lcd-amend-self-review-0001')
            ->postJson('/api/libro-digital/v1/amendments/'.$amendmentId.'/approve', ['note' => 'No debe aprobarse por sí mismo.'])
            ->assertForbidden()->assertJsonPath('code', 'LCD_AMENDMENT_SEPARATION_OF_DUTIES');
        $this->actingAs($reviewer)->withHeader('Idempotency-Key', 'lcd-amend-approve-0001')
            ->postJson('/api/libro-digital/v1/amendments/'.$amendmentId.'/approve', ['note' => 'Antecedente contrastado y corrección procedente.'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->withHeader('Idempotency-Key', 'lcd-amend-reviewer-apply-0001')
            ->postJson('/api/libro-digital/v1/amendments/'.$amendmentId.'/apply')
            ->assertForbidden()->assertJsonPath('code', 'LCD_AMENDMENT_SEPARATION_OF_DUTIES');
        $applied = $this->actingAs($applier)->withHeader('Idempotency-Key', 'lcd-amend-apply-0001')
            ->postJson('/api/libro-digital/v1/amendments/'.$amendmentId.'/apply')
            ->assertOk()
            ->assertJsonPath('data.amendment.status', 'applied')
            ->assertJsonPath('data.revision.revision', 2);

        $fresh = $session->fresh();
        $this->assertSame('Corrección formal con evidencia revisada.', $fresh->observation);
        $this->assertSame('amended', $fresh->status->value);
        $this->assertSame(2, (int) $fresh->revision);
        $this->assertSame(2, (int) $fresh->lock_version);
        $this->assertNull($fresh->canonical_hash);
        $this->assertDatabaseHas('lcd_daily_attendance_closures', ['book_id' => $book->id, 'revision' => 1, 'status' => 'reopened']);
        $this->assertDatabaseHas('lcd_monthly_attendance_closures', ['book_id' => $book->id, 'revision' => 1, 'status' => 'reopened']);
        $this->assertDatabaseCount('lcd_closure_reopenings', 2);
        $this->assertDatabaseHas('lcd_record_revisions', [
            'amendment_request_id' => $amendmentId,
            'revisable_type' => ClassSession::class,
            'revisable_id' => $session->id,
            'revision' => 2,
        ]);
        $this->assertNotEmpty($applied->json('data.revision.payload_hash'));

        $this->withHeaders(['Idempotency-Key' => 'lcd-amend-reclose-before-sign-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/daily-close', [
                'date' => $date,
                'teaching_group_id' => $group->id,
                'lock_version' => 1,
            ])->assertUnprocessable()->assertJsonPath('code', 'LCD_DAILY_CLOSURE_ANOMALIES');
        $fresh->forceFill(['status' => 'signed', 'canonical_hash' => hash('sha256', 'resigned')])->save();
        $this->withHeaders(['Idempotency-Key' => 'lcd-amend-reclose-day-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/daily-close', [
                'date' => $date,
                'teaching_group_id' => $group->id,
                'lock_version' => 1,
            ])->assertCreated()->assertJsonPath('data.status', 'reclosed')->assertJsonPath('data.revision', 2);
        $this->withHeaders(['Idempotency-Key' => 'lcd-amend-reclose-month-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/attendance/monthly-close', [
                'month' => $month,
                'teaching_group_id' => $group->id,
                'lock_version' => 1,
            ])->assertCreated()->assertJsonPath('data.status', 'reclosed')->assertJsonPath('data.revision', 2);

        $this->assertDatabaseHas('lcd_closure_reopenings', ['scope' => 'daily_attendance', 'status' => 'reclosed', 'replacement_revision' => 2]);
        $this->assertDatabaseHas('lcd_closure_reopenings', ['scope' => 'monthly_attendance', 'status' => 'reclosed', 'replacement_revision' => 2]);
        $this->assertDatabaseCount('lcd_daily_attendance_closures', 2);
        $this->assertDatabaseCount('lcd_monthly_attendance_closures', 2);
    }

    /**
     * @return array{User, User, User, School, AcademicYear, ScheduleSubject, Staff, StudentProfile, Book, TeachingGroup, RosterSnapshot, RosterSnapshotItem, ClassSession}
     */
    private function context(): array
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $users = collect(range(1, 3))->map(function (int $index) use ($role): User {
            $user = User::factory()->create(['active' => true, 'name' => 'Actor LCD '.$index]);
            $user->roles()->syncWithoutDetaching([$role->id]);

            return $user;
        });
        [$requester, $reviewer, $applier] = $users->all();
        $school = School::query()->create(['rbd' => '12345-6', 'name' => 'Escuela Prueba', 'timezone' => 'America/Santiago', 'active' => true]);
        $currentYear = (int) now('America/Santiago')->format('Y');
        $year = AcademicYear::factory()->create([
            'year' => $currentYear,
            'name' => (string) $currentYear,
            'starts_at' => $currentYear.'-01-01',
            'ends_at' => $currentYear.'-12-31',
            'is_active' => true,
        ]);
        $level = EducationLevel::factory()->create(['name' => 'Quinto básico de prueba', 'order' => 505, 'type' => 'basica']);
        $course = CourseSection::factory()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'display_name' => '5° Básico A',
            'section_name' => 'A',
        ]);
        $subject = ScheduleSubject::query()->create(['name' => 'Lenguaje', 'code' => 'LEN-05', 'area' => 'Lenguaje', 'color' => '#405189', 'active' => true]);
        $teacher = Staff::query()->create(['full_name' => 'Docente Prueba', 'rut' => '12.345.678-5', 'active' => true]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-LCD-CLOSURES',
            'name' => 'Perfil normativo de cierres',
            'version' => '1.0',
            'effective_from' => $currentYear.'-01-01',
            'retention_years' => 6,
            'rules_snapshot' => [
                'education_types' => ['basica', 'media'],
                'attendance' => [
                    'daily_resolution' => 'any_pedagogical_presence',
                    'requires_session_signature' => true,
                ],
            ],
            'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id,
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);
        FeatureFlag::query()->create(['school_id' => $school->id, 'scope_key' => 'school:'.$school->id, 'code' => 'lcd_enabled', 'enabled' => true]);
        $student = StudentProfile::factory()->create(['registered_name' => 'Estudiante Prueba']);
        StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'matriculada',
            'enrolled_at' => now('America/Santiago')->startOfDay(),
            'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $level->name,
            'snapshot_section_name' => $course->section_name,
            'snapshot_course_display_name' => $course->display_name,
        ]);

        $this->actingAs($requester)->withHeader('Idempotency-Key', 'lcd-closure-context-book-0001')
            ->postJson('/api/libro-digital/v1/books', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'course_section_id' => $course->id,
                'schedule_subject_id' => $subject->id,
                'teacher_staff_id' => $teacher->id,
                'normative_profile_id' => $profile->id,
                'name' => 'Libro para cierres',
                'modality' => 'regular',
            ])->assertCreated();
        $book = Book::query()->firstOrFail();
        $book->forceFill(['status' => 'open', 'opened_at' => now('UTC')])->save();
        $group = TeachingGroup::query()->where('book_id', $book->id)->firstOrFail();
        $snapshot = RosterSnapshot::query()->where('book_id', $book->id)->firstOrFail();
        $item = RosterSnapshotItem::query()->with('enrollmentLink')->where('roster_snapshot_id', $snapshot->id)->firstOrFail();
        $sessionDate = now('America/Santiago')->toDateString();
        $session = ClassSession::query()->create([
            'school_id' => $school->id,
            'book_id' => $book->id,
            'teaching_group_id' => $group->id,
            'academic_year_id' => $year->id,
            'regulatory_profile_id' => $profile->id,
            'roster_snapshot_id' => $snapshot->id,
            'schedule_subject_id' => $subject->id,
            'scheduled_teacher_id' => $teacher->id,
            'actual_teacher_id' => $teacher->id,
            'session_date' => $sessionDate,
            'status' => 'signed',
            'class_type' => 'regular',
            'teacher_name_snapshot' => $teacher->full_name,
            'subject_snapshot' => $subject->name,
            'course_snapshot' => $course->display_name,
            'objective_summary' => 'Objetivo pedagógico registrado.',
            'occurrence_key' => hash('sha256', $group->id.'|'.$sessionDate.'|1'),
            'canonical_hash' => hash('sha256', 'signed-session'),
            'revision' => 1,
            'lock_version' => 1,
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
        ]);
        $recordedAt = now('UTC')->addSecond();
        $attendancePayload = [
            'class_session_id' => $session->id,
            'roster_snapshot_item_id' => $item->id,
            'student_profile_id' => $item->student_profile_id,
            'student_enrollment_id' => $item->student_enrollment_id,
            'status' => 'present',
            'justification_status' => 'not_required',
            'source' => 'manual',
            'recorded_by' => $requester->id,
            'recorded_at' => $recordedAt,
            'revision' => 1,
        ];
        SessionAttendance::query()->create([
            ...$attendancePayload,
            'record_hash' => app(CanonicalJson::class)->hash($attendancePayload),
        ]);

        return [$requester, $reviewer, $applier, $school, $year, $subject, $teacher, $student, $book->fresh(), $group, $snapshot, $item, $session];
    }
}
