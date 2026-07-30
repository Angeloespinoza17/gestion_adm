<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceAnnualPlan;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceVisit;
use App\Models\MaintenanceWorkOrder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_report_requires_permission_and_consolidates_operational_statistics_without_mutating_records(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');

        $dependency = MaintenanceDependency::query()->create([
            'dependency_kind' => MaintenanceDependency::KIND_SPACE,
            'code' => 'DEP-01',
            'name' => 'Edificio principal',
            'active' => true,
            'is_maintenance_location' => true,
            'is_inventory_auditable' => true,
        ]);
        $technicalArea = MaintenanceDependency::query()->create([
            'dependency_kind' => MaintenanceDependency::KIND_TECHNICAL_ASSET,
            'parent_dependency_id' => $dependency->id,
            'code' => 'TEC-01',
            'name' => 'Tablero eléctrico',
            'active' => true,
            'is_maintenance_location' => false,
            'is_inventory_auditable' => false,
        ]);

        $completed = $this->createWorkOrder($dependency, $technicalArea, [
            'reported_at' => '2026-07-01',
            'due_date' => '2026-07-05',
            'assigned_to' => 'Técnico Uno',
            'priority' => 'Media',
            'status' => 'Terminado',
            'description' => 'Reparación de luminaria.',
        ]);
        $completed->timestamps = false;
        $completed->forceFill(['created_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-04 16:00:00'])->save();

        $this->createWorkOrder($dependency, $technicalArea, [
            'reported_at' => '2026-07-10',
            'due_date' => '2026-07-15',
            'assigned_to' => 'Técnico Uno, Técnico Dos',
            'priority' => 'Crítico',
            'status' => 'En proceso',
            'description' => 'Falla crítica en tablero.',
        ]);
        $this->createWorkOrder($dependency, null, [
            'reported_at' => '2026-07-20',
            'due_date' => '2026-08-05',
            'assigned_to' => null,
            'priority' => 'Alta',
            'status' => 'Sin comenzar',
            'description' => 'Revisión de puerta de acceso.',
        ]);
        $this->createWorkOrder($dependency, null, [
            'reported_at' => '2026-06-15',
            'due_date' => '2026-06-20',
            'assigned_to' => 'Técnico Dos',
            'priority' => 'Baja',
            'status' => 'Terminado',
            'description' => 'OT del período anterior.',
        ]);

        MaintenanceVisit::query()->create([
            'maintenance_dependency_id' => $dependency->id,
            'responsible' => 'Técnico Uno',
            'visit_date' => '2026-07-12',
            'visit_time' => '10:00',
            'visit_type' => 'Inspección',
            'status' => 'Finalizada',
        ]);
        MaintenanceVisit::query()->create([
            'maintenance_dependency_id' => $dependency->id,
            'responsible' => 'Técnico Dos',
            'visit_date' => '2026-07-25',
            'visit_time' => '11:00',
            'visit_type' => 'Mantención',
            'status' => 'Programada',
        ]);

        MaintenanceAnnualPlan::query()->create([
            'maintenance_dependency_id' => $dependency->id,
            'item_type' => 'technical_area',
            'technical_area_id' => $technicalArea->id,
            'planned_year' => 2026,
            'planned_month' => 7,
            'category' => 'Eléctrica',
            'responsible' => 'Técnico Uno',
            'frequency' => 'Mensual',
            'status' => 'Cumplida',
            'title' => 'Inspección de tablero',
            'scheduled_date' => '2026-07-05',
            'completed_date' => '2026-07-05',
            'alert_days' => 30,
            'alert_enabled' => true,
        ]);
        MaintenanceAnnualPlan::query()->create([
            'maintenance_dependency_id' => $dependency->id,
            'item_type' => 'dependency',
            'planned_year' => 2026,
            'planned_month' => 7,
            'category' => 'Infraestructura',
            'responsible' => 'Técnico Dos',
            'frequency' => 'Mensual',
            'status' => 'Programada',
            'title' => 'Revisión preventiva de accesos',
            'scheduled_date' => '2026-07-18',
            'alert_days' => 30,
            'alert_enabled' => true,
        ]);

        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        Sanctum::actingAs($user);

        $reportUrl = '/api/maintenance/reports?start_date=2026-07-01&end_date=2026-07-31'
            .'&dependency_id='.$dependency->id;

        $this->getJson($reportUrl)
            ->assertForbidden();

        $role = Role::query()->create(['name' => 'Reportes Mantención', 'slug' => 'reportes-mantencion', 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'ver_reportes_mantencion'],
            [
                'name' => 'Ver reportes Mantención',
                'active' => true,
            ]
        );
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $countsBefore = [
            MaintenanceWorkOrder::count(),
            MaintenanceVisit::count(),
            MaintenanceAnnualPlan::count(),
        ];

        $response = $this->getJson($reportUrl);

        $response
            ->assertOk()
            ->assertJsonPath('summary.work_orders_total', 3)
            ->assertJsonPath('summary.open_total', 2)
            ->assertJsonPath('summary.completed_total', 1)
            ->assertJsonPath('summary.overdue_open_total', 1)
            ->assertJsonPath('summary.critical_open_total', 1)
            ->assertJsonPath('summary.unassigned_open_total', 1)
            ->assertJsonPath('summary.on_time_rate', 100)
            ->assertJsonPath('summary.visits_total', 2)
            ->assertJsonPath('summary.visits_completed_total', 1)
            ->assertJsonPath('summary.plans_total', 2)
            ->assertJsonPath('summary.plans_completed_total', 1)
            ->assertJsonPath('summary.plans_overdue_total', 1)
            ->assertJsonPath('rankings.dependencies.0.label', 'DEP-01 · Edificio principal')
            ->assertJsonPath('rankings.dependencies.0.total', 3)
            ->assertJsonPath('rankings.technical_areas.0.label', 'TEC-01 · Tablero eléctrico')
            ->assertJsonPath('rankings.technical_areas.0.total', 2)
            ->assertJsonPath('metadata.costs_included', false)
            ->assertJsonPath('metadata.detail_truncated', false);

        $assignees = collect($response->json('rankings.assignees'))->keyBy('label');
        $this->assertSame(2, $assignees->get('Técnico Uno')['total']);
        $this->assertSame(1, $assignees->get('Técnico Dos')['total']);
        $this->assertSame(1, $assignees->get('Sin asignar')['total']);

        $this->assertSame($countsBefore, [
            MaintenanceWorkOrder::count(),
            MaintenanceVisit::count(),
            MaintenanceAnnualPlan::count(),
        ]);
    }

    public function test_report_filters_work_orders_by_responsible_and_dependency(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');
        $dependency = MaintenanceDependency::query()->create([
            'dependency_kind' => MaintenanceDependency::KIND_SPACE,
            'code' => 'DEP-FILTER',
            'name' => 'Dependencia filtro',
            'active' => true,
            'is_maintenance_location' => true,
            'is_inventory_auditable' => true,
        ]);
        $this->createWorkOrder($dependency, null, [
            'reported_at' => '2026-07-08',
            'assigned_to' => 'Responsable A, Responsable B',
            'priority' => 'Alta',
            'status' => 'En proceso',
            'description' => 'Orden compartida.',
        ]);
        $this->createWorkOrder($dependency, null, [
            'reported_at' => '2026-07-09',
            'assigned_to' => 'Responsable C',
            'priority' => 'Baja',
            'status' => 'Sin comenzar',
            'description' => 'Orden individual.',
        ]);

        $user = $this->authorizedUser();
        Sanctum::actingAs($user);

        $this->getJson(
            '/api/maintenance/reports?start_date=2026-07-01&end_date=2026-07-31'
            .'&dependency_id='.$dependency->id.'&assignee='.urlencode('Responsable B')
        )
            ->assertOk()
            ->assertJsonPath('summary.work_orders_total', 1)
            ->assertJsonPath('work_orders.0.description', 'Orden compartida.');
    }

    private function createWorkOrder(
        MaintenanceDependency $dependency,
        ?MaintenanceDependency $technicalArea,
        array $attributes
    ): MaintenanceWorkOrder {
        return MaintenanceWorkOrder::query()->create([
            'maintenance_dependency_id' => $dependency->id,
            'technical_area_id' => $technicalArea?->id,
            'requested_by' => 'Solicitante de prueba',
            'dependency_component' => 'Elemento de prueba',
            'reported_at' => $attributes['reported_at'],
            'assigned_to' => $attributes['assigned_to'],
            'priority' => $attributes['priority'],
            'status' => $attributes['status'],
            'due_date' => $attributes['due_date'] ?? null,
            'description' => $attributes['description'],
        ]);
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create(['name' => 'Reportes Mantención', 'slug' => 'reportes-mantencion', 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'ver_reportes_mantencion'],
            [
                'name' => 'Ver reportes Mantención',
                'active' => true,
            ]
        );
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
