<?php

namespace Tests\Feature\StudentHealth;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Inspectoria\InspectoriaCourseAssignment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SocialWork\MedicalCertificate;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentMedicalLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_shared_register_creates_temporary_and_permanent_leaves_and_filters_chronic_students(): void
    {
        $user = $this->superAdmin();
        $student = StudentProfile::query()->create([
            'first_name' => 'Camila',
            'last_name' => 'Rojas',
            'registered_name' => 'Camila Rojas',
            'rut' => '22.111.222-3',
            'general_status' => 'activo',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-08-21',
            'ends_on' => null,
            'reason' => 'Reposo por cuadro respiratorio.',
            'is_permanent' => false,
            'source_module' => 'infirmary',
        ])->assertUnprocessable()->assertJsonValidationErrors('ends_on');

        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-08-21',
            'ends_on' => '2026-08-25',
            'reason' => 'Reposo por cuadro respiratorio.',
            'is_permanent' => false,
            'source_module' => 'infirmary',
        ])->assertCreated()
            ->assertJsonPath('data.student.name', 'Camila Rojas')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.source_module', 'infirmary');

        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-08-21',
            'ends_on' => null,
            'reason' => 'Diabetes tipo 1 con control permanente.',
            'is_permanent' => true,
            'source_module' => 'inspectoria',
        ])->assertCreated()
            ->assertJsonPath('data.is_permanent', true)
            ->assertJsonPath('data.status', 'permanent');

        $this->getJson('/api/student-medical-leaves?permanent=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.reason', 'Diabetes tipo 1 con control permanente.')
            ->assertJsonPath('summary.total_records', 2)
            ->assertJsonPath('summary.chronic_students', 1)
            ->assertJsonPath('capabilities.can_create', true);

        $this->assertDatabaseHas('student_medical_certificates', [
            'student_profile_id' => $student->id,
            'is_permanent' => true,
            'source_module' => 'inspectoria',
            'registered_by' => $user->id,
            'private_path' => null,
        ]);
    }

    public function test_user_without_shared_medical_leave_permission_cannot_read_or_create(): void
    {
        $user = User::factory()->create(['active' => true]);
        $student = StudentProfile::query()->create([
            'first_name' => 'Laura',
            'last_name' => 'Díaz',
            'rut' => '23.111.222-4',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/student-medical-leaves')->assertForbidden();
        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-08-21',
            'ends_on' => '2026-08-22',
            'reason' => 'Control médico.',
            'is_permanent' => false,
            'source_module' => 'infirmary',
        ])->assertForbidden();
    }

    public function test_authorized_user_can_upload_and_download_a_private_medical_attachment(): void
    {
        Storage::fake('local');
        $user = $this->superAdmin();
        $student = StudentProfile::query()->create([
            'first_name' => 'Fernanda',
            'last_name' => 'Molina',
            'registered_name' => 'Fernanda Molina',
            'rut' => '24.222.333-5',
            'general_status' => 'activo',
        ]);
        Sanctum::actingAs($user);

        $response = $this->post('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-08-21',
            'ends_on' => '2026-08-24',
            'reason' => 'Reposo indicado por profesional tratante.',
            'is_permanent' => false,
            'source_module' => 'infirmary',
            'attachment' => UploadedFile::fake()->create('licencia-medica.pdf', 280, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.attachment.name', 'licencia-medica.pdf')
            ->assertJsonPath('data.attachment.mime_type', 'application/pdf')
            ->assertJsonPath('data.attachment.size_bytes', 280 * 1024)
            ->assertJsonMissingPath('data.private_path')
            ->assertJsonMissingPath('data.attachment.private_path')
            ->assertJsonMissingPath('data.attachment.sha256');

        $certificate = MedicalCertificate::query()->sole();
        $privatePath = $certificate->getRawOriginal('private_path');
        $this->assertNotNull($privatePath);
        $this->assertStringStartsWith('student-health/medical-leaves/', $privatePath);
        Storage::disk('local')->assertExists($privatePath);
        $this->assertSame($user->id, $certificate->uploaded_by);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($privatePath)), $certificate->sha256);

        $this->get("/api/student-medical-leaves/{$certificate->id}/attachment")
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
    }

    public function test_medical_attachment_rejects_unsupported_files(): void
    {
        Storage::fake('local');
        $user = $this->superAdmin();
        $student = StudentProfile::query()->create([
            'first_name' => 'Javiera',
            'last_name' => 'Soto',
            'rut' => '25.222.333-6',
        ]);
        Sanctum::actingAs($user);

        $this->post('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-08-21',
            'ends_on' => '2026-08-22',
            'reason' => 'Documento con formato no permitido.',
            'is_permanent' => false,
            'source_module' => 'inspectoria',
            'attachment' => UploadedFile::fake()->create('archivo.exe', 12, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');

        $this->assertDatabaseCount('student_medical_certificates', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_temporary_period_cannot_overlap_a_record_created_from_the_other_module(): void
    {
        Storage::fake('local');
        $user = $this->superAdmin();
        $student = StudentProfile::query()->create([
            'first_name' => 'Martina',
            'last_name' => 'Silva',
            'registered_name' => 'Martina Silva',
            'rut' => '24.111.222-5',
            'general_status' => 'activo',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-08-21',
            'ends_on' => '2026-08-25',
            'reason' => 'Licencia ingresada por Enfermería.',
            'is_permanent' => false,
            'source_module' => 'infirmary',
        ])->assertCreated();

        foreach ([
            ['2026-08-20', '2026-08-21'],
            ['2026-08-22', '2026-08-24'],
            ['2026-08-25', '2026-08-27'],
        ] as [$startsOn, $endsOn]) {
            $this->post('/api/student-medical-leaves', [
                'student_profile_id' => $student->id,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'reason' => 'Intento duplicado desde Inspectoría.',
                'is_permanent' => false,
                'source_module' => 'inspectoria',
                'attachment' => UploadedFile::fake()->create('respaldo-duplicado.pdf', 24, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertUnprocessable()
                ->assertJsonValidationErrors('starts_on')
                ->assertJsonPath(
                    'errors.starts_on.0',
                    'Ya existe una licencia o certificado temporal para esta alumna entre el 21-08-2026 y el 25-08-2026, ingresado desde Enfermería. Revisa el registro compartido antes de volver a ingresarlo.',
                );
        }

        $this->assertSame([], Storage::disk('local')->allFiles('student-health/medical-leaves'));

        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-08-26',
            'ends_on' => '2026-08-27',
            'reason' => 'Nuevo período sin superposición.',
            'is_permanent' => false,
            'source_module' => 'inspectoria',
        ])->assertCreated();

        $this->assertDatabaseCount('student_medical_certificates', 2);
    }

    public function test_permanent_condition_does_not_block_a_temporary_medical_leave(): void
    {
        $user = $this->superAdmin();
        $student = StudentProfile::query()->create([
            'first_name' => 'Emilia',
            'last_name' => 'Fuentes',
            'registered_name' => 'Emilia Fuentes',
            'rut' => '25.111.222-6',
            'general_status' => 'activo',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-03-01',
            'ends_on' => null,
            'reason' => 'Condición crónica informada.',
            'is_permanent' => true,
            'source_module' => 'inspectoria',
        ])->assertCreated();

        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $student->id,
            'starts_on' => '2026-08-21',
            'ends_on' => '2026-08-23',
            'reason' => 'Licencia temporal compatible con el antecedente crónico.',
            'is_permanent' => false,
            'source_module' => 'infirmary',
        ])->assertCreated();

        $this->assertDatabaseCount('student_medical_certificates', 2);
    }

    public function test_inspector_only_sees_and_registers_students_from_assigned_courses(): void
    {
        Storage::fake('local');
        [$year, $assignedCourse, $assignedStudent] = $this->academicContext('A', 'Antonia Soto');
        [, $otherCourse, $otherStudent] = $this->academicContext('B', 'Josefina Pérez', $year);

        $staff = Staff::query()->create([
            'full_name' => 'Inspectora curso A',
            'rut' => '12.345.678-5',
            'status' => 'activo',
            'active' => true,
        ]);
        $user = User::factory()->create(['active' => true, 'staff_id' => $staff->id]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'inspectoria'],
            ['name' => 'Inspector/a', 'active' => true],
        );
        $permissions = Permission::query()->whereIn('slug', [
            'ver_licencias_medicas_estudiantes',
            'crear_licencias_medicas_estudiantes',
            'editar_licencias_medicas_estudiantes',
        ])->pluck('id');
        $role->permissions()->syncWithoutDetaching($permissions);
        $user->roles()->attach($role);

        InspectoriaCourseAssignment::query()->create([
            'academic_year_id' => $year->id,
            'course_section_id' => $assignedCourse->id,
            'inspector_staff_id' => $staff->id,
            'starts_on' => '2026-03-01',
            'active' => true,
        ]);

        $assignedCertificate = $this->certificate($assignedStudent, 'Licencia visible');
        $otherCertificate = $this->certificate($otherStudent, 'Licencia de otro curso');
        foreach ([$assignedCertificate, $otherCertificate] as $certificate) {
            $path = "student-health/medical-leaves/{$certificate->id}.pdf";
            Storage::disk('local')->put($path, 'private-pdf');
            $certificate->forceFill([
                'private_path' => $path,
                'original_name' => 'licencia.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 11,
                'sha256' => hash('sha256', 'private-pdf'),
            ])->save();
        }
        Sanctum::actingAs($user);

        $this->getJson('/api/student-medical-leaves')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.student.id', $assignedStudent->id)
            ->assertJsonMissing(['reason' => 'Licencia de otro curso']);

        $this->getJson('/api/student-medical-leaves/students?search=Antonia')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedStudent->id);
        $this->getJson('/api/student-medical-leaves/students?search=Josefina')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->get("/api/student-medical-leaves/{$assignedCertificate->id}/attachment")->assertOk();
        $this->get("/api/student-medical-leaves/{$otherCertificate->id}/attachment")->assertNotFound();

        $this->putJson("/api/student-medical-leaves/{$otherCertificate->id}", [
            'starts_on' => '2026-08-21',
            'ends_on' => '2026-08-24',
            'reason' => 'Intento de edición fuera del curso asignado.',
            'is_permanent' => false,
        ])->assertNotFound();

        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $otherStudent->id,
            'starts_on' => '2026-08-21',
            'ends_on' => '2026-08-22',
            'reason' => 'Intento fuera de alcance.',
            'is_permanent' => false,
            'source_module' => 'inspectoria',
        ])->assertForbidden();

        $this->postJson('/api/student-medical-leaves', [
            'student_profile_id' => $assignedStudent->id,
            'starts_on' => '2026-08-24',
            'ends_on' => '2026-08-25',
            'reason' => 'Reposo autorizado.',
            'is_permanent' => false,
            'source_module' => 'inspectoria',
        ])->assertCreated();

        $createdCertificate = MedicalCertificate::query()
            ->where('student_profile_id', $assignedStudent->id)
            ->where('administrative_summary', 'Reposo autorizado.')
            ->sole();

        $this->post("/api/student-medical-leaves/{$createdCertificate->id}", [
            '_method' => 'PUT',
            'starts_on' => '2026-08-24',
            'ends_on' => '2026-08-27',
            'reason' => 'Reposo autorizado con fecha corregida y respaldo incorporado.',
            'is_permanent' => false,
            'attachment' => UploadedFile::fake()->create('licencia-corregida.pdf', 96, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.ends_on', '2026-08-27')
            ->assertJsonPath('data.reason', 'Reposo autorizado con fecha corregida y respaldo incorporado.')
            ->assertJsonPath('data.source_module', 'inspectoria')
            ->assertJsonPath('data.attachment.name', 'licencia-corregida.pdf')
            ->assertJsonPath('data.updated_by', $user->name)
            ->assertJsonMissingPath('data.attachment.private_path')
            ->assertJsonMissingPath('data.attachment.sha256');

        $createdCertificate->refresh();
        $this->assertSame($user->id, $createdCertificate->updated_by);
        $this->assertSame($user->id, $createdCertificate->uploaded_by);
        $this->assertSame('2026-08-27', $createdCertificate->covers_to?->format('Y-m-d'));
        Storage::disk('local')->assertExists($createdCertificate->getRawOriginal('private_path'));
    }

    public function test_create_permission_does_not_allow_editing_without_the_dedicated_permission(): void
    {
        $student = StudentProfile::query()->create([
            'first_name' => 'Paz',
            'last_name' => 'Contreras',
            'rut' => '23.444.555-6',
        ]);
        $certificate = $this->certificate($student, 'Registro protegido');
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create(['slug' => 'registro_sin_edicion', 'name' => 'Registro sin edición', 'active' => true]);
        $role->permissions()->attach(Permission::query()->where('slug', 'crear_licencias_medicas_estudiantes')->sole());
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $this->putJson("/api/student-medical-leaves/{$certificate->id}", [
            'starts_on' => '2026-08-21',
            'ends_on' => '2026-08-25',
            'reason' => 'Cambio no autorizado.',
            'is_permanent' => false,
        ])->assertForbidden();

        $this->assertDatabaseHas('student_medical_certificates', [
            'id' => $certificate->id,
            'administrative_summary' => 'Registro protegido',
            'updated_by' => null,
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true],
        );
        $user->roles()->attach($role);

        return $user;
    }

    /** @return array{AcademicYear, CourseSection, StudentProfile} */
    private function academicContext(string $section, string $studentName, ?AcademicYear $year = null): array
    {
        $year ??= AcademicYear::query()->create([
            'name' => 'Año escolar 2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $level = EducationLevel::query()->firstOrCreate(
            ['name' => '1° medio'],
            ['type' => 'media', 'order' => 90, 'active' => true],
        );
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => $section,
            'display_name' => "1° medio {$section}",
            'active' => true,
        ]);
        [$firstName, $lastName] = explode(' ', $studentName, 2);
        $student = StudentProfile::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'registered_name' => $studentName,
            'rut' => $section === 'A' ? '21.111.222-3' : '22.111.222-4',
            'general_status' => 'activo',
        ]);
        StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'regular',
            'enrolled_at' => '2026-03-01',
            'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $level->name,
            'snapshot_section_name' => $section,
            'snapshot_course_display_name' => $course->display_name,
        ]);

        return [$year, $course, $student];
    }

    private function certificate(StudentProfile $student, string $reason): MedicalCertificate
    {
        return MedicalCertificate::query()->create([
            'student_profile_id' => $student->id,
            'issued_on' => '2026-08-21',
            'covers_from' => '2026-08-21',
            'covers_to' => '2026-08-23',
            'certificate_type' => 'licencia_medica',
            'administrative_summary' => $reason,
            'status' => 'vigente',
            'is_permanent' => false,
            'source_module' => 'infirmary',
        ]);
    }
}
