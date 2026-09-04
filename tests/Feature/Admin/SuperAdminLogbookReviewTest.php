<?php

namespace Tests\Feature\Admin;

use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Infirmary\InfirmaryDailyLog;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Models\PorterDailyLogEntry;
use App\Models\Role;
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
            ->assertJsonPath('total', 4)
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.today', 4)
            ->assertJsonPath('summary.follow_up', 2)
            ->assertJsonPath('summary.high_priority', 2)
            ->assertJsonPath('summary.by_source.inspectoria', 1)
            ->assertJsonPath('summary.by_source.porter', 1)
            ->assertJsonPath('summary.by_source.infirmary', 1)
            ->assertJsonPath('summary.by_source.convivencia', 1)
            ->assertJsonCount(4, 'sources')
            ->assertJsonCount(4, 'data');

        $this->assertSame(
            ['convivencia', 'infirmary', 'porter', 'inspectoria'],
            collect($response->json('data'))->pluck('source')->all(),
        );
        $this->assertSame('Acción de resguardo aplicada.', $response->json('data.0.extra.immediate_action'));
        $this->assertTrue($response->json('data.0.is_sensitive'));
        $this->assertSame('Coordinación con apoderada.', $response->json('data.1.extra.action_taken'));
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
    }
}
