<?php

namespace Tests\Feature\Maintenance;

use App\Models\AcademicYear;
use App\Models\Attendance\SchoolDay;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceVisit;
use App\Models\MaintenanceVisitPlanningBatch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceVisitPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Staff $staff;

    /** @var array<int, MaintenanceDependency> */
    private array $dependencies;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $superAdminRole = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true]
        );
        $this->superAdmin->roles()->attach($superAdminRole);

        $this->staff = Staff::query()->create([
            'full_name' => 'Funcionario Planificador',
            'rut' => '12.345.678-5',
            'active' => true,
            'can_receive_maintenance_orders' => true,
            'maintenance_role' => 'encargado_mantencion',
        ]);

        $this->dependencies = collect(range(1, 3))
            ->map(fn (int $number) => MaintenanceDependency::query()->create([
                'dependency_kind' => MaintenanceDependency::KIND_SPACE,
                'code' => 'DEP-PLAN-'.$number,
                'name' => 'Dependencia planificada '.$number,
                'active' => true,
                'is_maintenance_location' => true,
            ]))
            ->all();
    }

    public function test_planning_endpoints_are_exclusive_to_super_admin_even_with_visit_permission(): void
    {
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create(['name' => 'Gestor visitas', 'slug' => 'gestor-visitas-test', 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'gestionar_visitas_mantencion'],
            ['name' => 'Gestionar visitas', 'active' => true]
        );
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $this->postJson('/api/maintenance/visits/planning/preview', [
            'configuration' => $this->configuration(),
        ])->assertForbidden();

        $this->postJson('/api/maintenance/visits/planning/confirm', [
            'configuration' => $this->configuration(),
            'idempotency_key' => (string) Str::uuid(),
            'rows' => [[
                'maintenance_dependency_id' => $this->dependencies[0]->id,
                'responsible_staff_id' => $this->staff->id,
                'visit_date' => '2026-09-07',
                'visit_time' => '09:00',
                'visit_type' => 'Inspección',
            ]],
        ])->assertForbidden();
    }

    public function test_preview_balances_dependencies_and_excludes_non_school_days_without_writing(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $academicYear = AcademicYear::query()->create([
            'name' => 'Año 2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
        ]);
        SchoolDay::query()->create([
            'academic_year_id' => $academicYear->id,
            'date' => '2026-09-02',
            'is_school_day' => false,
            'status' => 'confirmed',
            'source' => 'test',
            'label' => 'Jornada institucional',
        ]);

        MaintenanceVisit::query()->create([
            'maintenance_dependency_id' => $this->dependencies[0]->id,
            'responsible' => $this->staff->full_name,
            'responsible_staff_id' => $this->staff->id,
            'visit_date' => '2026-09-07',
            'visit_time' => '09:00',
            'visit_type' => 'Inspección',
            'status' => 'Programada',
        ]);

        $countsBefore = [MaintenanceVisit::count(), MaintenanceVisitPlanningBatch::count()];
        $response = $this->postJson('/api/maintenance/visits/planning/preview', [
            'configuration' => $this->configuration(),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.proposed', 3)
            ->assertJsonPath('data.summary.dependencies', 3)
            ->assertJsonPath('data.summary.unscheduled', 0)
            ->assertJsonPath('data.summary.excluded_school_dates', 1);

        $rows = collect($response->json('data.rows'));
        $this->assertCount(3, $rows);
        $this->assertCount(3, $rows->pluck('maintenance_dependency_id')->unique());
        $this->assertFalse($rows->contains(fn (array $row) => $row['visit_date'] === '2026-09-02'));
        $this->assertTrue($rows->every(function (array $row): bool {
            return in_array(CarbonImmutable::parse($row['visit_date'])->isoWeekday(), [1, 3], true);
        }));
        $this->assertSame($countsBefore, [MaintenanceVisit::count(), MaintenanceVisitPlanningBatch::count()]);
    }

    public function test_confirmation_is_transactional_auditable_and_idempotent(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $secondStaff = Staff::query()->create([
            'full_name' => 'Funcionario Reasignado',
            'rut' => '9.876.543-3',
            'active' => true,
            'can_receive_maintenance_orders' => true,
            'maintenance_role' => 'apoyo_operativo',
        ]);
        $configuration = $this->configuration();
        $preview = $this->postJson('/api/maintenance/visits/planning/preview', compact('configuration'))
            ->assertOk()
            ->json('data.rows');
        $preview[1]['responsible_staff_id'] = $secondStaff->id;
        $idempotencyKey = (string) Str::uuid();
        $payload = [
            'configuration' => $configuration,
            'idempotency_key' => $idempotencyKey,
            'rows' => $preview,
        ];

        $first = $this->postJson('/api/maintenance/visits/planning/confirm', $payload);
        $first
            ->assertCreated()
            ->assertJsonPath('data.confirmed_count', 3)
            ->assertJsonPath('data.status', 'confirmed');

        $batchId = $first->json('data.id');
        $this->assertDatabaseHas('maintenance_visit_planning_batches', [
            'id' => $batchId,
            'idempotency_key' => $idempotencyKey,
            'created_by_user_id' => $this->superAdmin->id,
            'confirmed_count' => 3,
        ]);
        $this->assertSame(3, MaintenanceVisit::query()->where('planning_batch_id', $batchId)->count());
        $this->assertSame(2, MaintenanceVisit::query()->where('responsible_staff_id', $this->staff->id)->count());
        $this->assertDatabaseHas('maintenance_visits', [
            'planning_batch_id' => $batchId,
            'responsible_staff_id' => $secondStaff->id,
            'responsible' => 'Funcionario Reasignado',
        ]);

        $this->postJson('/api/maintenance/visits/planning/confirm', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $batchId);

        $this->assertSame(1, MaintenanceVisitPlanningBatch::count());
        $this->assertSame(3, MaintenanceVisit::count());
    }

    public function test_new_conflict_after_preview_blocks_the_complete_batch_and_preserves_existing_visits(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $configuration = $this->configuration();
        $rows = $this->postJson('/api/maintenance/visits/planning/preview', compact('configuration'))
            ->assertOk()
            ->json('data.rows');

        MaintenanceVisit::query()->create([
            'maintenance_dependency_id' => $rows[0]['maintenance_dependency_id'],
            'responsible' => 'Otro funcionario',
            'visit_date' => $rows[0]['visit_date'],
            'visit_time' => '15:00',
            'visit_type' => 'Reunión',
            'status' => 'Programada',
        ]);

        $this->postJson('/api/maintenance/visits/planning/confirm', [
            'configuration' => $configuration,
            'idempotency_key' => (string) Str::uuid(),
            'rows' => $rows,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rows.0');

        $this->assertSame(1, MaintenanceVisit::count());
        $this->assertSame(0, MaintenanceVisitPlanningBatch::count());
    }

    public function test_confirmation_keeps_weekend_and_daily_capacity_rules_after_manual_edits(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $configuration = $this->configuration();
        $rows = $this->postJson('/api/maintenance/visits/planning/preview', compact('configuration'))
            ->assertOk()
            ->json('data.rows');
        $rows[0]['visit_date'] = '2026-09-05';

        $this->postJson('/api/maintenance/visits/planning/confirm', [
            'configuration' => $configuration,
            'idempotency_key' => (string) Str::uuid(),
            'rows' => $rows,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rows.0.visit_date');

        $this->assertSame(0, MaintenanceVisit::count());
        $this->assertSame(0, MaintenanceVisitPlanningBatch::count());
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'responsible_staff_id' => $this->staff->id,
            'dependency_ids' => collect($this->dependencies)->pluck('id')->all(),
            'frequency' => 'weekly',
            'interval_value' => 1,
            'weekdays' => [1, 3],
            'month_day' => 1,
            'max_visits_per_day' => 2,
            'visits_per_dependency' => 1,
            'start_time' => '09:00',
            'slot_minutes' => 30,
            'visit_type' => 'Inspección',
            'notes' => 'Planificación preventiva automática.',
            'exclude_weekends' => true,
            'exclude_non_school_days' => true,
        ];
    }
}
