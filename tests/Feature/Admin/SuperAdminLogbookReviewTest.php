<?php

namespace Tests\Feature\Admin;

use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Infirmary\InfirmaryDailyLog;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Models\Operational\OperationalStaffLogEntry;
use App\Models\PorterDailyLogEntry;
use App\Models\Role;
use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityIncidentStatus;
use App\Models\Security\SecurityRound;
use App\Models\Security\SecurityRoundSector;
use App\Models\Security\SecurityShift;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminLogbookReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-28 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_superadmin_reviews_every_operational_logbook_in_one_paginated_timeline(): void
    {
        $user = $this->superAdmin();
        Sanctum::actingAs($user);
        $this->createRecords($user);

        $response = $this->getJson('/api/superadmin/logbooks?per_page=20')
            ->assertOk()
            ->assertJsonPath('total', 7)
            ->assertJsonPath('summary.total', 7)
            ->assertJsonPath('summary.today', 7)
            ->assertJsonPath('summary.follow_up', 4)
            ->assertJsonPath('summary.high_priority', 4)
            ->assertJsonPath('summary.by_source.inspectoria', 1)
            ->assertJsonPath('summary.by_source.porter', 1)
            ->assertJsonPath('summary.by_source.infirmary', 1)
            ->assertJsonPath('summary.by_source.convivencia', 1)
            ->assertJsonPath('summary.by_source.staff_logbook', 1)
            ->assertJsonPath('summary.by_source.security_rounds', 1)
            ->assertJsonPath('summary.by_source.security_incidents', 1)
            ->assertJsonCount(7, 'sources')
            ->assertJsonCount(7, 'data');

        $this->assertSame(
            ['security_incidents', 'security_rounds', 'staff_logbook', 'convivencia', 'infirmary', 'porter', 'inspectoria'],
            collect($response->json('data'))->pluck('source')->all(),
        );
        $this->assertSame('Fuga de agua detectada', $response->json('data.0.title'));
        $this->assertSame('Incidencias nocturnas', $response->json('data.0.source_label'));
        $this->assertSame('ACT-NOCHE-001', $response->json('data.0.extra.act_number'));
        $this->assertSame('José Campos', $response->json('data.0.extra.nochero_name'));
        $this->assertSame('Ronda nocturna #1 · José Campos', $response->json('data.1.title'));
        $this->assertSame('Laboratorio', $response->json('data.1.extra.sectors.0.name'));
        $this->assertSame('Fuga de agua detectada', $response->json('data.1.extra.incidents.0.title'));
        $this->assertSame('Acuerdo de coordinación institucional', $response->json('data.2.title'));
        $this->assertSame('Acción de resguardo aplicada.', $response->json('data.3.extra.immediate_action'));
        $this->assertTrue($response->json('data.3.is_sensitive'));
        $this->assertSame('Coordinación con apoderada.', $response->json('data.4.extra.action_taken'));
    }

    public function test_review_filters_at_source_before_paginating_and_searches_across_its_content(): void
    {
        $user = $this->superAdmin();
        Sanctum::actingAs($user);
        $this->createRecords($user);

        $this->getJson('/api/superadmin/logbooks?source=porter&search=portón')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.source', 'porter')
            ->assertJsonPath('data.0.title', 'Apertura extraordinaria de portón')
            ->assertJsonPath('summary.by_source.porter', 1);

        $this->getJson('/api/superadmin/logbooks?priority=urgente')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.source', 'infirmary');

        $this->getJson('/api/superadmin/logbooks?source=staff_logbook&search=coordinación')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.source', 'staff_logbook')
            ->assertJsonPath('data.0.source_label', 'Funcionarios')
            ->assertJsonPath('data.0.category', 'Reunión o acuerdo')
            ->assertJsonPath('data.0.author.name', $user->name);

        $this->getJson('/api/superadmin/logbooks?source=security_rounds&search=ACT-NOCHE-001')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.source', 'security_rounds')
            ->assertJsonPath('data.0.source_label', 'Nocheros')
            ->assertJsonPath('data.0.priority', 'critica')
            ->assertJsonPath('data.0.requires_follow_up', true);

        $this->getJson('/api/superadmin/logbooks?priority=critica')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.source', 'security_incidents')
            ->assertJsonPath('data.1.source', 'security_rounds');

        $this->getJson('/api/superadmin/logbooks?source=security_incidents&search=Fuga')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.source', 'security_incidents')
            ->assertJsonPath('data.0.source_label', 'Incidencias nocturnas')
            ->assertJsonPath('data.0.extra.sector_name', 'Laboratorio')
            ->assertJsonPath('data.0.extra.responsible', $user->name)
            ->assertJsonPath('data.0.extra.requires_immediate_attention', true);

        $this->getJson('/api/superadmin/logbooks?source=security_incidents&status=pendiente')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.status', 'pendiente');

        $this->getJson('/api/superadmin/logbooks?date_from=2026-08-29&date_to=2026-08-28')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }

    public function test_logbook_review_is_exclusive_to_active_superadmins(): void
    {
        $this->getJson('/api/superadmin/logbooks')->assertUnauthorized();

        $regularUser = User::factory()->create(['active' => true]);
        $regularRole = Role::query()->create([
            'slug' => 'administrador_revision_test',
            'name' => 'Administrador de prueba',
            'active' => true,
        ]);
        $regularUser->roles()->attach($regularRole);
        Sanctum::actingAs($regularUser);
        $this->getJson('/api/superadmin/logbooks')->assertForbidden();

        $inactiveSuperAdmin = $this->superAdmin(active: false);
        Sanctum::actingAs($inactiveSuperAdmin);
        $this->getJson('/api/superadmin/logbooks')->assertForbidden();
    }

    public function test_superadmin_navigation_exposes_the_new_parent_and_first_tool(): void
    {
        $user = $this->superAdmin();
        Sanctum::actingAs($user);

        $modules = collect($this->getJson('/api/me/modules')->assertOk()->json('data'));

        $parent = $modules->firstWhere('slug', 'superadmin');
        $tool = $modules->firstWhere('slug', 'superadmin_logbook_review');

        $this->assertNotNull($parent);
        $this->assertNotNull($tool);
        $this->assertSame('/superadmin/bitacoras', $tool['frontend_route']);
        $this->assertSame($parent['id'], $tool['parent_id']);
    }

    private function superAdmin(bool $active = true): User
    {
        $user = User::factory()->create(['active' => $active]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->attach($role);

        return $user;
    }

    private function createRecords(User $user): void
    {
        InspectoriaDailyLog::query()->create([
            'registered_by_user_id' => $user->id,
            'happened_at' => '2026-08-28 08:15:00',
            'category' => 'asistencia',
            'priority' => 'alta',
            'status' => 'en_seguimiento',
            'title' => 'Revisión de ingreso de estudiantes',
            'detail' => 'Se registró una situación de asistencia al inicio de la jornada.',
            'requires_follow_up' => true,
            'follow_up_note' => 'Revisar al cierre de la jornada.',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        PorterDailyLogEntry::query()->create([
            'registered_by' => $user->id,
            'logged_on' => '2026-08-28',
            'logged_at' => '2026-08-28 09:10:00',
            'shift_label' => 'Mañana',
            'category' => 'incidencia',
            'priority' => 'media',
            'status' => 'registrado',
            'title' => 'Apertura extraordinaria de portón',
            'detail' => 'Se coordinó un acceso excepcional con el equipo directivo.',
        ]);

        InfirmaryDailyLog::query()->create([
            'registered_by_user_id' => $user->id,
            'happened_at' => '2026-08-28 10:20:00',
            'category' => 'contacto_apoderado',
            'priority' => 'urgente',
            'status' => 'en_seguimiento',
            'title' => 'Atención relevante de jornada',
            'detail' => 'Se realizó una atención de salud que requiere continuidad.',
            'action_taken' => 'Coordinación con apoderada.',
            'requires_follow_up' => true,
            'follow_up_note' => 'Confirmar evolución durante la tarde.',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ConvivenciaDailyLog::query()->create([
            'inspector_user_id' => $user->id,
            'happened_at' => '2026-08-28 11:25:00',
            'daily_log_type_label' => 'Situación de convivencia',
            'place' => 'Patio central',
            'description' => 'Se observó una situación que requirió mediación inmediata.',
            'immediate_action' => 'Acción de resguardo aplicada.',
            'guardian_informed' => true,
            'status' => 'registrado',
            'is_sensitive' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        OperationalStaffLogEntry::query()->create([
            'owner_user_id' => $user->id,
            'owner_name_snapshot' => $user->name,
            'occurred_at' => '2026-08-28 11:50:00',
            'category' => 'meeting',
            'title' => 'Acuerdo de coordinación institucional',
            'details' => 'Se registró un acuerdo para coordinar la próxima jornada.',
        ]);

        $nightStaff = Staff::query()->create([
            'full_name' => 'José Campos',
            'rut' => '17.765.432-1',
            'status' => 'activo',
            'active' => true,
        ]);
        $shift = SecurityShift::query()->create([
            'staff_id' => $nightStaff->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'scheduled_start_at' => '2026-08-28 22:00:00',
            'scheduled_end_at' => '2026-08-29 07:00:00',
            'status' => SecurityShift::STATUS_EN_CURSO,
            'coverage_label' => 'Todo el colegio',
        ]);
        $round = SecurityRound::query()->create([
            'security_shift_id' => $shift->id,
            'recorded_by_user_id' => $user->id,
            'round_number' => 1,
            'recorded_at' => '2026-08-28 11:55:00',
            'overall_status' => SecurityRound::STATUS_REQUIERE_ATENCION,
            'observations' => 'Se detectó una novedad que necesita seguimiento.',
            'nochero_confirmation_name' => 'José Campos',
            'latitude' => '-39.8196000',
            'longitude' => '-73.2452000',
            'location_accuracy' => '8.50',
            'act_number' => 'ACT-NOCHE-001',
            'act_generated_at' => '2026-08-28 11:55:00',
        ]);
        $sector = SecurityRoundSector::query()->create([
            'security_round_id' => $round->id,
            'sector_name' => 'Laboratorio',
            'sector_state' => 'riesgo_detectado',
            'observations' => 'Humedad visible junto al acceso.',
            'display_order' => 1,
        ]);
        $pendingStatus = SecurityIncidentStatus::query()->where('code', 'pendiente')->firstOrFail();
        SecurityIncident::query()->create([
            'security_shift_id' => $shift->id,
            'security_round_id' => $round->id,
            'security_round_sector_id' => $sector->id,
            'reported_by_user_id' => $user->id,
            'status_id' => $pendingStatus->id,
            'current_responsible_user_id' => $user->id,
            'priority' => SecurityIncident::PRIORITY_CRITICA,
            'title' => 'Fuga de agua detectada',
            'description' => 'Se aisló preventivamente el sector y se informó la novedad.',
            'sector_name' => 'Laboratorio',
            'requires_immediate_attention' => true,
            'response_due_at' => '2026-08-28 13:00:00',
            'response_summary' => 'Se notificó a mantención para contener la fuga.',
        ]);
    }
}
