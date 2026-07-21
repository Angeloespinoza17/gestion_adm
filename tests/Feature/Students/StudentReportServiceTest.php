<?php

namespace Tests\Feature\Students;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentMovement;
use App\Models\StudentProfile;
use App\Services\Students\StudentReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_stock_flow_quality_and_course_metrics_for_the_selected_period(): void
    {
        [$year, $course] = $this->createAcademicContext();
        $activeStudent = $this->createStudent('Antonia', 'Activa', [
            'rut' => '11.111.111-1',
            'birthdate' => '2015-05-10',
            'address' => 'Calle Uno 123',
            'commune' => 'Santiago',
            'guardian_name' => 'Apoderada Uno',
            'guardian_phone' => '911111111',
            'nationality' => 'Chilena',
            'is_pie_participant' => true,
            'ethnicity' => 'Mapuche',
            'religion' => 'Catolicismo',
            'accepts_religion_classes' => true,
            'guardian_relationship' => 'Madre',
            'guardian_backup_relationship' => 'Tía',
            'guardian_commune' => 'Santiago',
            'guardian_backup_commune' => 'Providencia',
            'guardian_photo_authorization' => true,
            'guardian_backup_photo_authorization' => false,
            'lives_with' => 'Madre y padre',
            'guardian_occupation' => 'Profesora',
            'guardian_marital_status' => 'Soltera',
            'guardian_education_level' => 'Superior completa',
            'father_name' => 'Padre Uno',
            'father_occupation' => 'Técnico',
            'father_education_level' => 'Técnica completa',
            'mother_name' => 'Madre Uno',
            'mother_occupation' => 'Profesora',
            'mother_education_level' => 'Superior completa',
            'health_insurance' => 'Fonasa',
            'has_chronic_illness' => true,
            'fit_for_physical_education' => true,
        ]);
        $withdrawnStudent = $this->createStudent('Beatriz', 'Retirada', [
            'rut' => '22.222.222-2',
            'general_status' => 'retirado',
        ]);

        $activeEnrollment = $this->createEnrollment($activeStudent, $year, $course, 'regular', '2026-03-05');
        $withdrawnEnrollment = $this->createEnrollment($withdrawnStudent, $year, $course, 'retirada', '2026-04-03', '2026-04-20');

        StudentEnrollmentMovement::query()->create([
            'student_enrollment_id' => $withdrawnEnrollment->id,
            'student_profile_id' => $withdrawnStudent->id,
            'academic_year_id' => $year->id,
            'from_course_section_id' => $course->id,
            'movement_type' => 'retiro',
            'effective_date' => '2026-04-20',
            'from_status' => 'regular',
            'to_status' => 'retirada',
            'snapshot_year_name' => '2026',
            'snapshot_from_course_display_name' => '5° básico A',
        ]);

        $report = app(StudentReportService::class)->build([
            'academic_year_id' => $year->id,
            'period' => 'custom',
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]);

        $this->assertSame(2, $report['summary']['registered_students']);
        $this->assertSame(1, $report['summary']['active_enrollments']);
        $this->assertSame(1, $report['summary']['new_enrollments']);
        $this->assertSame(1, $report['summary']['withdrawals']);
        $this->assertSame(0, $report['summary']['withdrawals_without_effective_date']);
        $this->assertSame(1, $report['summary']['pie_students']);
        $this->assertSame(50.0, $report['summary']['retention_rate']);
        $this->assertSame(100.0, $report['summary']['completeness_rate']);
        $this->assertSame(['2026-04'], $report['trends']['categories']->all());
        $this->assertSame([1], $report['trends']['series']['enrollments']->all());
        $this->assertSame([1], $report['trends']['series']['withdrawals']->all());
        $this->assertSame(1, $report['distributions']['by_course']->first()['total']);
        $this->assertSame('Mapuche', $report['distributions']['ethnicity']->first()['label']);
        $this->assertSame('Catolicismo', $report['distributions']['religion']['affiliations']->first()['label']);
        $this->assertSame('Madre', $report['distributions']['family']['guardian_relationships']->first()['label']);
        $this->assertSame('Santiago', $report['distributions']['family']['student_communes']->first()['label']);
        $this->assertSame('Madre y padre', $report['distributions']['family']['lives_with']->first()['label']);
        $this->assertSame('Santiago', $report['distributions']['family']['guardian_communes']->first()['label']);
        $this->assertSame('Providencia', $report['distributions']['family']['backup_guardian_communes']->first()['label']);
        $this->assertSame(1, $report['distributions']['family']['guardian_photo_authorizations']->firstWhere('label', 'Autoriza')['total']);
        $this->assertSame(1, $report['distributions']['family']['backup_guardian_photo_authorizations']->firstWhere('label', 'No autoriza')['total']);
        $this->assertSame(1, $report['distributions']['health']['conditions'][0]['yes']);
        $this->assertNull($report['distributions']['infirmary']);
        $this->assertCount(2, $report['details']);
        $this->assertSame(100, collect($report['details'])->firstWhere('id', $activeEnrollment->student_profile_id)['quality_score']);
    }

    public function test_it_counts_an_imported_withdrawal_without_date_or_movement(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        try {
            [$year, $course] = $this->createAcademicContext();
            $student = $this->createStudent('Isidora', 'Importada', ['general_status' => 'retirado']);
            $this->createEnrollment($student, $year, $course, 'retirada', '2026-03-04');

            $report = app(StudentReportService::class)->build([
                'academic_year_id' => $year->id,
                'period' => 'academic_year',
            ]);

            $julyIndex = collect($report['trends']['categories'])->search('2026-07');

            $this->assertSame(1, $report['summary']['registered_students']);
            $this->assertSame(0, $report['summary']['active_enrollments']);
            $this->assertSame(1, $report['summary']['withdrawals']);
            $this->assertSame(1, $report['summary']['withdrawals_without_effective_date']);
            $this->assertNotFalse($julyIndex);
            $this->assertSame(1, $report['trends']['series']['withdrawals'][$julyIndex]);
            $this->assertSame(1, array_sum($report['trends']['series']['withdrawals']->all()));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_applies_course_and_pie_filters_to_stock_and_detail(): void
    {
        [$year, $course, $otherCourse] = $this->createAcademicContext(true);
        $pieStudent = $this->createStudent('Camila', 'PIE', ['is_pie_participant' => true]);
        $otherStudent = $this->createStudent('Daniela', 'Otra', ['is_pie_participant' => false]);

        $this->createEnrollment($pieStudent, $year, $course, 'regular', '2026-03-01');
        $this->createEnrollment($otherStudent, $year, $otherCourse, 'regular', '2026-03-01');

        $report = app(StudentReportService::class)->build([
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'is_pie_participant' => true,
        ]);

        $this->assertSame(1, $report['summary']['registered_students']);
        $this->assertSame(1, $report['summary']['active_enrollments']);
        $this->assertSame('Camila PIE', $report['details']->first()['name']);
        $this->assertCount(1, $report['distributions']['by_course']);
        $this->assertSame($course->id, $report['distributions']['by_course']->first()['id']);
    }

    public function test_it_returns_students_with_missing_data_for_a_report_dimension(): void
    {
        [$year, $course] = $this->createAcademicContext();
        $completeStudent = $this->createStudent('Carolina', 'Completa', ['birthdate' => '2016-04-12']);
        $missingStudent = $this->createStudent('Dominga', 'Pendiente');

        $this->createEnrollment($completeStudent, $year, $course, 'regular', '2026-03-01');
        $this->createEnrollment($missingStudent, $year, $course, 'regular', '2026-03-01');

        $result = app(StudentReportService::class)->missingData([
            'academic_year_id' => $year->id,
            'dimension' => 'age',
        ]);

        $this->assertSame('Distribución por edad', $result['meta']['label']);
        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame('Dominga Pendiente', $result['data']->first()['name']);
        $this->assertSame(['Fecha de nacimiento'], $result['data']->first()['missing_fields']);
    }

    public function test_it_identifies_the_student_and_each_missing_parent_field(): void
    {
        [$year, $course] = $this->createAcademicContext();
        $completeStudent = $this->createStudent('Emilia', 'Completa', [
            'father_education_level' => 'Media completa',
            'father_nationality' => 'Chilena',
            'mother_nationality' => 'Chilena',
        ]);
        $missingStudent = $this->createStudent('Florencia', 'Pendiente', [
            'father_nationality' => 'Chilena',
        ]);

        $this->createEnrollment($completeStudent, $year, $course, 'regular', '2026-03-01');
        $this->createEnrollment($missingStudent, $year, $course, 'regular', '2026-03-01');

        $fatherEducation = app(StudentReportService::class)->missingData([
            'academic_year_id' => $year->id,
            'dimension' => 'father_education_levels',
        ]);
        $parentNationalities = app(StudentReportService::class)->missingData([
            'academic_year_id' => $year->id,
            'dimension' => 'parent_nationalities',
        ]);

        $this->assertSame('Florencia Pendiente', $fatherEducation['data']->first()['name']);
        $this->assertSame(['Nivel educacional del padre'], $fatherEducation['data']->first()['missing_fields']);
        $this->assertSame('Florencia Pendiente', $parentNationalities['data']->first()['name']);
        $this->assertSame(['Nacionalidad de la madre'], $parentNationalities['data']->first()['missing_fields']);
    }

    public function test_it_integrates_authorized_infirmary_statistics_for_the_same_students_and_period(): void
    {
        [$year, $course] = $this->createAcademicContext();
        $student = $this->createStudent('Elena', 'Salud');
        $this->createEnrollment($student, $year, $course, 'regular', '2026-03-01');

        $attention = InfirmaryAttention::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'attention_category' => 'dolor_cabeza',
            'attended_at' => '2026-04-10 10:00:00',
            'student_full_name_snapshot' => 'Elena Salud',
            'course_name_snapshot' => $course->display_name,
            'consultation_reason' => 'Dolor de cabeza',
            'priority' => 'media',
            'status' => 'finalizada',
        ]);
        InfirmaryAccident::query()->create([
            'attention_id' => $attention->id,
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'occurred_at' => '2026-04-10 09:50:00',
            'accident_type' => 'caida',
            'description' => 'Caída leve',
            'severity' => 'leve',
        ]);
        InfirmaryAttentionReferral::query()->create([
            'attention_id' => $attention->id,
            'referral_type' => 'regresa_a_sala',
            'referred_at' => '2026-04-10 10:20:00',
        ]);

        $report = app(StudentReportService::class)->build([
            'academic_year_id' => $year->id,
            'period' => 'custom',
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ], true);

        $this->assertTrue($report['meta']['capabilities']['infirmary_statistics']);
        $this->assertSame(1, $report['distributions']['infirmary']['summary']['students_attended']);
        $this->assertSame(1, $report['distributions']['infirmary']['summary']['attentions']);
        $this->assertSame(1, $report['distributions']['infirmary']['summary']['accidents']);
        $this->assertSame(1, $report['distributions']['infirmary']['summary']['referrals']);
        $this->assertSame('dolor_cabeza', $report['distributions']['infirmary']['attentions_by_category']->first()['label']);
    }

    public function test_summary_is_cached_without_shipping_detail_and_can_be_refreshed(): void
    {
        [$year, $course] = $this->createAcademicContext();
        $firstStudent = $this->createStudent('Francisca', 'Primera');
        $this->createEnrollment($firstStudent, $year, $course, 'regular', '2026-03-01');
        $service = app(StudentReportService::class);
        $filters = ['academic_year_id' => $year->id];

        $firstReport = $service->summary($filters, false, true);

        $secondStudent = $this->createStudent('Gabriela', 'Segunda');
        $this->createEnrollment($secondStudent, $year, $course, 'regular', '2026-03-02');
        $cachedReport = $service->summary($filters);
        $refreshedReport = $service->summary($filters, false, true);

        $this->assertSame([], $firstReport['details']);
        $this->assertSame(1, $cachedReport['summary']['registered_students']);
        $this->assertSame(2, $refreshedReport['summary']['registered_students']);
        $this->assertSame(180, $refreshedReport['meta']['cache_ttl_seconds']);
    }

    public function test_detail_is_filtered_sorted_and_paginated_on_the_server(): void
    {
        [$year, $course] = $this->createAcademicContext();

        foreach (range(1, 12) as $index) {
            $student = $this->createStudent(
                $index === 7 ? 'Objetivo' : 'Estudiante',
                str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                ['commune' => $index === 7 ? 'Providencia' : 'Santiago'],
            );
            $this->createEnrollment($student, $year, $course, 'regular', '2026-03-01');
        }

        $service = app(StudentReportService::class);
        $secondPage = $service->details([
            'academic_year_id' => $year->id,
            'page' => 2,
            'per_page' => 10,
            'sort' => 'name',
            'direction' => 'asc',
        ]);
        $filtered = $service->details([
            'academic_year_id' => $year->id,
            'detail_search' => 'Providencia',
        ]);

        $this->assertSame(12, $secondPage['meta']['total']);
        $this->assertSame(2, $secondPage['meta']['current_page']);
        $this->assertCount(2, $secondPage['data']);
        $this->assertSame(1, $filtered['meta']['total']);
        $this->assertSame('Objetivo 07', $filtered['data']->first()['name']);
    }

    private function createAcademicContext(bool $withOtherCourse = false): array
    {
        $year = AcademicYear::query()->create([
            'name' => 'Año académico 2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
        ]);
        $level = EducationLevel::query()->create([
            'name' => '5° básico',
            'order' => 7,
            'type' => 'basica',
        ]);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '5° básico A',
            'capacity' => 35,
            'active' => true,
        ]);

        if (! $withOtherCourse) {
            return [$year, $course];
        }

        $otherCourse = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'B',
            'display_name' => '5° básico B',
            'capacity' => 35,
            'active' => true,
        ]);

        return [$year, $course, $otherCourse];
    }

    private function createStudent(string $firstName, string $lastName, array $attributes = []): StudentProfile
    {
        return StudentProfile::query()->create(array_merge([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'general_status' => 'activo',
        ], $attributes));
    }

    private function createEnrollment(
        StudentProfile $student,
        AcademicYear $year,
        CourseSection $course,
        string $status,
        string $enrolledAt,
        ?string $withdrawnAt = null,
    ): StudentEnrollment {
        return StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => $status,
            'enrolled_at' => $enrolledAt,
            'withdrawn_at' => $withdrawnAt,
            'snapshot_year_name' => $year->name,
            'snapshot_level_name' => '5° básico',
            'snapshot_section_name' => $course->section_name,
            'snapshot_course_display_name' => $course->display_name,
        ]);
    }
}
