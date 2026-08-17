<?php

namespace Tests\Feature\HumanResources;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Models\HumanResources\HrAbsenceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HumanResourcesOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $hrUser;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->staff = Staff::query()->create([
            'full_name' => 'Funcionaria de Prueba',
            'rut' => '12345678-5',
            'status' => 'activo',
            'active' => true,
        ]);
        $this->hrUser = User::factory()->create(['name' => 'RRHH Prueba', 'active' => true]);
        $this->attachRole($this->hrUser, 'rrhh_prueba', [
            'rrhh.ausencias.ver', 'rrhh.ausencias.gestionar', 'rrhh.ausencias.importar', 'rrhh.ausencias.exportar',
            'rrhh.seleccion.ver', 'rrhh.seleccion.gestionar', 'rrhh.seleccion.importar', 'rrhh.psicolaborales.confidencial',
        ]);
    }

    public function test_external_administrative_day_updates_the_annual_balance(): void
    {
        Sanctum::actingAs($this->hrUser);

        $absence = $this->postJson('/api/human-resources/absences', [
            'staff_id' => $this->staff->id,
            'absence_type' => 'dia_administrativo',
            'starts_on' => '2026-08-14',
            'ends_on' => '2026-08-14',
            'quantity' => 0.5,
            'unit' => 'dias',
            'rest_type' => 'medio_dia',
            'status' => 'justificada',
            'affects_attendance' => true,
            'affects_payroll' => false,
            'notes' => 'Solicitado fuera de plataforma.',
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('hr_absence_balance_movements', [
            'absence_record_id' => $absence['id'],
            'bucket' => 'administrativo',
            'quantity' => -0.5,
        ]);

        $this->putJson('/api/human-resources/absence-balances/'.$this->staff->id, [
            'year' => 2026,
            'administrative_entitlement' => 6,
            'administrative_adjustment' => 0,
            'compensatory_entitlement' => 0,
            'compensatory_adjustment' => 0,
        ])->assertOk();

        $this->getJson('/api/human-resources/absences?year=2026')
            ->assertOk()
            ->assertJsonPath('data.summary.records', 1)
            ->assertJsonPath('data.balances.0.administrative_used', 0.5)
            ->assertJsonPath('data.balances.0.administrative_available', 5.5);

        $this->deleteJson('/api/human-resources/absences/'.$absence['id'])->assertOk();
        $this->getJson('/api/human-resources/absences?year=2026')
            ->assertOk()
            ->assertJsonPath('data.summary.records', 0);
        $this->assertDatabaseMissing('hr_absence_balance_movements', ['absence_record_id' => $absence['id']]);
    }

    public function test_absence_calendar_consolidates_sources_and_records_overlapping_the_visible_range(): void
    {
        Sanctum::actingAs($this->hrUser);
        HrAbsenceRecord::query()->create([
            'staff_id' => $this->staff->id,
            'absence_type' => 'licencia_medica',
            'starts_on' => '2026-07-29',
            'ends_on' => '2026-08-04',
            'quantity' => 7,
            'unit' => 'dias',
            'status' => 'tramitada',
            'source' => 'plataforma',
            'affects_attendance' => true,
        ]);
        HrAbsenceRecord::query()->create([
            'staff_id' => $this->staff->id,
            'absence_type' => 'dia_administrativo',
            'starts_on' => '2026-08-14',
            'ends_on' => '2026-08-14',
            'quantity' => 1,
            'unit' => 'dias',
            'status' => 'justificada',
            'source' => 'manual_fuera_plataforma',
            'affects_attendance' => true,
        ]);
        HrAbsenceRecord::query()->create([
            'staff_id' => $this->staff->id,
            'absence_type' => 'otro',
            'starts_on' => '2026-09-02',
            'ends_on' => '2026-09-02',
            'quantity' => 1,
            'unit' => 'dias',
            'status' => 'registrada',
            'source' => 'importacion_historica',
        ]);

        $this->getJson('/api/human-resources/absences/calendar?date_from=2026-08-01&date_to=2026-08-31')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.source', 'plataforma')
            ->assertJsonPath('data.1.source', 'manual_fuera_plataforma');
    }

    public function test_hourly_absence_calculates_its_duration_and_keeps_the_calendar_schedule(): void
    {
        Sanctum::actingAs($this->hrUser);

        $absence = $this->postJson('/api/human-resources/absences', [
            'staff_id' => $this->staff->id,
            'absence_type' => 'permiso_autorizado',
            'starts_on' => '2026-08-18',
            'ends_on' => '2026-08-18',
            'starts_at' => '09:15',
            'ends_at' => '11:45',
            'quantity' => 99,
            'unit' => 'horas',
            'status' => 'justificada',
            'affects_attendance' => true,
            'affects_payroll' => false,
        ])->assertCreated()->json('data');

        $this->assertSame('2.50', $absence['quantity']);
        $this->assertDatabaseHas('hr_absence_records', [
            'id' => $absence['id'],
            'starts_at' => '09:15',
            'ends_at' => '11:45',
            'quantity' => 2.5,
            'unit' => 'horas',
        ]);

        $this->getJson('/api/human-resources/absences/calendar?date_from=2026-08-01&date_to=2026-08-31')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.starts_at', '09:15')
            ->assertJsonPath('data.0.ends_at', '11:45')
            ->assertJsonPath('data.0.unit', 'horas');

        $this->postJson('/api/human-resources/absences', [
            'staff_id' => $this->staff->id,
            'absence_type' => 'permiso_autorizado',
            'starts_on' => '2026-08-19',
            'ends_on' => '2026-08-19',
            'starts_at' => '12:00',
            'ends_at' => '11:45',
            'unit' => 'horas',
            'status' => 'justificada',
        ])->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    }

    public function test_recruitment_pipeline_links_candidate_vacancy_application_and_interview(): void
    {
        Sanctum::actingAs($this->hrUser);

        $candidate = $this->postJson('/api/human-resources/recruitment/candidates', [
            'full_name' => 'Candidata de Prueba',
            'email' => 'candidata@example.test',
            'phone' => '+56912345678',
            'desired_position' => 'Docente de Lenguaje',
            'status' => 'banco_talento',
        ])->assertCreated()->json('data');

        $this->post('/api/human-resources/recruitment/candidates/'.$candidate['id'].'/cv', [
            'file' => UploadedFile::fake()->create('curriculum.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $vacancy = $this->postJson('/api/human-resources/recruitment/vacancies', [
            'title' => 'Docente de Lenguaje',
            'vacancy_count' => 1,
            'opened_on' => '2026-08-12',
            'status' => 'entrevistas',
        ])->assertCreated()->json('data');

        $application = $this->postJson('/api/human-resources/recruitment/applications', [
            'vacancy_id' => $vacancy['id'],
            'cv_bank_entry_id' => $candidate['id'],
            'stage' => 'entrevista_psicolaboral',
            'applied_on' => '2026-08-12',
            'reconsideration' => 'si',
        ])->assertCreated()->json('data');

        $interview = $this->postJson('/api/human-resources/recruitment/interviews', [
            'application_id' => $application['id'],
            'completed_at' => '2026-08-13 10:00:00',
            'result' => 'apto_con_observaciones',
            'induction_required' => true,
            'reconsideration' => 'si',
            'considerations' => 'Apta con inducción inicial.',
            'confidential_notes' => 'Contenido reservado para RRHH.',
            'status' => 'completada',
        ])->assertCreated()->json('data');

        $this->post('/api/human-resources/recruitment/interviews/'.$interview['id'].'/report', [
            'file' => UploadedFile::fake()->create('informe.pdf', 180, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->getJson('/api/human-resources/recruitment')
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('data.summary.candidates', 1)
            ->assertJsonPath('data.summary.active_vacancies', 1)
            ->assertJsonPath('data.interviews.0.confidential_notes', 'Contenido reservado para RRHH.')
            ->assertJsonPath('data.interviews.0.report_available', true);
    }

    public function test_psycholaboral_confidential_data_requires_the_specific_permission(): void
    {
        Sanctum::actingAs($this->hrUser);
        $candidate = $this->postJson('/api/human-resources/recruitment/candidates', [
            'full_name' => 'Postulante Reservada',
            'status' => 'postulante',
        ])->assertCreated()->json('data');
        $application = $this->postJson('/api/human-resources/recruitment/applications', [
            'cv_bank_entry_id' => $candidate['id'],
            'stage' => 'entrevista_psicolaboral',
        ])->assertCreated()->json('data');
        $interview = $this->postJson('/api/human-resources/recruitment/interviews', [
            'application_id' => $application['id'],
            'result' => 'apto',
            'confidential_notes' => 'Evaluación reservada.',
            'status' => 'completada',
        ])->assertCreated()->json('data');
        $this->post('/api/human-resources/recruitment/interviews/'.$interview['id'].'/report', [
            'file' => UploadedFile::fake()->create('informe.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $viewer = User::factory()->create(['active' => true]);
        $this->attachRole($viewer, 'seleccion_lectura', ['rrhh.seleccion.ver']);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/human-resources/recruitment')
            ->assertOk()
            ->assertJsonMissingPath('data.interviews.0.confidential_notes');
        $this->get('/api/human-resources/recruitment/interviews/'.$interview['id'].'/report', ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    private function attachRole(User $user, string $roleSlug, array $permissionSlugs): void
    {
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], [
            'name' => ucfirst(str_replace('_', ' ', $roleSlug)),
            'description' => 'Rol creado para pruebas.',
            'active' => true,
        ]);
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'description' => 'Permiso de prueba.',
                'active' => true,
            ]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
