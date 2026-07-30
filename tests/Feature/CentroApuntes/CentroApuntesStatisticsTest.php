<?php

namespace Tests\Feature\CentroApuntes;

use App\Models\CentroApuntes\CentroApuntesAlerta;
use App\Models\CentroApuntes\CentroApuntesAsignatura;
use App\Models\CentroApuntes\CentroApuntesMaquina;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\Department;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\CentroApuntes\CentroApuntesDashboardService;
use App\Services\CentroApuntes\CentroApuntesSolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CentroApuntesStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-29 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_report_exposes_printed_sheets_by_user_department_subject_and_machine(): void
    {
        $administration = $this->createDepartment('Administración', 1);
        $pedagogy = $this->createDepartment('Unidad Técnico Pedagógica', 2);
        $viewer = $this->createRequester('Administrador', $administration, true);
        $teacher = $this->createRequester('Docente Matemática', $pedagogy);
        [$subject, $machine] = $this->createCatalogs($viewer);

        $this->createRequest($viewer, $administration, $subject, $machine, [
            'request_code' => 'CAP-00001',
            'requested_at' => '2026-07-05 09:00:00',
            'delivery_date' => '2026-07-06',
            'sheet_count' => 5,
            'copies_count' => 4,
            'estimated_total_impressions' => 20,
            'status' => 'entregada',
            'delivered_at' => '2026-07-06 10:00:00',
        ]);
        $this->createRequest($viewer, $administration, $subject, $machine, [
            'request_code' => 'CAP-00002',
            'requested_at' => '2026-07-10 09:00:00',
            'delivery_date' => '2026-07-15',
            'sheet_count' => 10,
            'copies_count' => 2,
            'estimated_total_impressions' => 20,
            'status' => 'en_proceso',
            'is_urgent' => true,
            'priority' => 'urgente',
        ]);
        $this->createRequest($teacher, $pedagogy, $subject, $machine, [
            'request_code' => 'CAP-00003',
            'requested_at' => '2026-07-20 09:00:00',
            'delivery_date' => '2026-07-21',
            'sheet_count' => 3,
            'copies_count' => 10,
            'estimated_total_impressions' => 30,
            'status' => 'entregada',
            'delivered_at' => '2026-07-23 10:00:00',
        ]);
        $this->createRequest($viewer, $administration, $subject, $machine, [
            'request_code' => 'CAP-00004',
            'requested_at' => '2026-06-15 09:00:00',
            'delivery_date' => '2026-06-16',
            'sheet_count' => 2,
            'copies_count' => 5,
            'estimated_total_impressions' => 10,
            'status' => 'entregada',
            'delivered_at' => '2026-06-16 10:00:00',
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/centro-apuntes/reportes?start_date=2026-07-01&end_date=2026-07-31');

        $response
            ->assertOk()
            ->assertJsonPath('summary.requests_total', 3)
            ->assertJsonPath('summary.original_pages_total', 18)
            ->assertJsonPath('summary.sheets_printed_total', 70)
            ->assertJsonPath('summary.delivered_total', 2)
            ->assertJsonPath('summary.on_time_rate', 50)
            ->assertJsonPath('summary.backlog_total', 1)
            ->assertJsonPath('summary.overdue_open_total', 1)
            ->assertJsonPath('charts.sheets_by_user.0.label', 'Administrador')
            ->assertJsonPath('charts.sheets_by_user.0.sheets_printed', 40)
            ->assertJsonPath('charts.sheets_by_department.0.label', 'Administración')
            ->assertJsonPath('charts.sheets_by_department.0.sheets_printed', 40)
            ->assertJsonPath('charts.sheets_by_subject.0.label', 'Matemática')
            ->assertJsonPath('charts.sheets_by_subject.0.sheets_printed', 70)
            ->assertJsonPath('charts.sheets_by_machine.0.label', 'Multifuncional principal')
            ->assertJsonPath('charts.sheets_by_machine.0.sheets_printed', 70)
            ->assertJsonPath('metadata.costs_included', false);

        $this->getJson('/api/centro-apuntes/reportes?start_date=2026-07-01&end_date=2026-07-31&department_id='.$pedagogy->id)
            ->assertOk()
            ->assertJsonPath('summary.requests_total', 1)
            ->assertJsonPath('summary.sheets_printed_total', 30)
            ->assertJsonPath('charts.sheets_by_user.0.label', 'Docente Matemática');
    }

    public function test_request_service_captures_primary_department_snapshot(): void
    {
        $department = $this->createDepartment('Convivencia Escolar', 1);
        $requester = $this->createRequester('Encargada Convivencia', $department);
        [$subject, $machine] = $this->createCatalogs($requester);

        $request = app(CentroApuntesSolicitudService::class)->create([
            'requested_by_user_id' => $requester->id,
            'subject_id' => $subject->id,
            'machine_id' => $machine->id,
            'task_type' => 'guia',
            'delivery_date' => '2026-07-31',
            'sheet_count' => 4,
            'copies_count' => 12,
            'paper_size' => 'carta',
            'priority' => 'normal',
        ], $requester);

        $this->assertSame($department->id, $request->department_id);
        $this->assertSame('Convivencia Escolar', $request->department_name_snapshot);
        $this->assertSame(48, $request->estimated_total_impressions);
    }

    public function test_dashboard_uses_physical_printed_sheets_and_operational_rankings_without_costs(): void
    {
        $department = $this->createDepartment('Unidad Técnico Pedagógica', 1);
        $user = $this->createRequester('Docente Lenguaje', $department);
        [$subject, $machine] = $this->createCatalogs($user);

        $this->createRequest($user, $department, $subject, $machine, [
            'request_code' => 'CAP-DASH-01',
            'requested_at' => '2026-07-08 09:00:00',
            'delivery_date' => '2026-07-10',
            'sheet_count' => 5,
            'copies_count' => 4,
            'estimated_total_impressions' => 20,
            'status' => 'entregada',
            'delivered_at' => '2026-07-10 11:00:00',
        ]);
        $this->createRequest($user, $department, $subject, $machine, [
            'request_code' => 'CAP-DASH-02',
            'requested_at' => '2026-07-20 09:00:00',
            'delivery_date' => '2026-07-31',
            'sheet_count' => 3,
            'copies_count' => 10,
            'estimated_total_impressions' => 30,
            'status' => 'en_proceso',
        ]);
        $this->createRequest($user, $department, $subject, $machine, [
            'request_code' => 'CAP-DASH-PREVIOUS',
            'requested_at' => '2026-06-10 09:00:00',
            'delivery_date' => '2026-06-11',
            'sheet_count' => 2,
            'copies_count' => 5,
            'estimated_total_impressions' => 10,
            'status' => 'entregada',
            'delivered_at' => '2026-06-11 10:00:00',
        ]);

        $dashboard = app(CentroApuntesDashboardService::class)->build();

        $this->assertSame(2, $dashboard['metrics']['month_requests']);
        $this->assertSame(8, $dashboard['metrics']['month_original_pages']);
        $this->assertSame(50, $dashboard['metrics']['month_printed_sheets']);
        $this->assertSame(1, $dashboard['metrics']['open_tasks']);
        $this->assertSame(100.0, $dashboard['metrics']['on_time_rate']);
        $this->assertSame('Docente Lenguaje', $dashboard['charts']['sheets_by_user'][0]['label']);
        $this->assertSame(50, $dashboard['charts']['sheets_by_user'][0]['printed_sheets']);
        $this->assertSame('Unidad Técnico Pedagógica', $dashboard['charts']['sheets_by_department'][0]['label']);
        $this->assertSame('Matemática', $dashboard['charts']['sheets_by_subject'][0]['label']);
        $this->assertSame('Multifuncional principal', $dashboard['charts']['sheets_by_machine'][0]['label']);
        $this->assertSame(400.0, $dashboard['comparison']['deltas']['month_printed_sheets']);
        $this->assertFalse($dashboard['metadata']['costs_included']);
    }

    public function test_dashboard_resolves_and_reuses_alerts_without_deleting_history(): void
    {
        $department = $this->createDepartment('Administración', 1);
        $user = $this->createRequester('Administrador', $department);
        [$subject, $machine] = $this->createCatalogs($user);
        $this->createRequest($user, $department, $subject, $machine, [
            'request_code' => 'CAP-ALERT',
            'requested_at' => '2026-07-29 08:00:00',
            'delivery_date' => '2026-07-30',
            'sheet_count' => 1,
            'copies_count' => 1,
            'estimated_total_impressions' => 1,
            'status' => 'pendiente',
        ]);

        $historicalAlert = CentroApuntesAlerta::query()->create([
            'alert_type' => 'pending_tasks',
            'alert_level' => 'warning',
            'title' => 'Alerta histórica',
            'message' => 'Registro histórico resuelto.',
            'status' => 'resuelta',
            'detected_at' => now()->subDay(),
            'resolved_at' => now()->subHours(12),
            'metadata' => ['count' => 2],
        ]);

        $service = app(CentroApuntesDashboardService::class);
        $service->build();
        $service->build();

        $this->assertDatabaseHas('centro_apuntes_alertas', [
            'id' => $historicalAlert->id,
            'status' => 'resuelta',
        ]);
        $this->assertSame(
            1,
            CentroApuntesAlerta::query()
                ->where('alert_type', 'pending_tasks')
                ->where('status', 'pendiente')
                ->count(),
        );
        $this->assertSame(2, CentroApuntesAlerta::query()->where('alert_type', 'pending_tasks')->count());
    }

    private function createDepartment(string $name, int $sortOrder): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    private function createRequester(string $name, Department $department, bool $superAdmin = false): User
    {
        $staff = Staff::query()->create([
            'full_name' => $name,
            'rut' => (string) random_int(10000000, 99999999).'-'.random_int(0, 9),
            'status' => 'activo',
            'active' => true,
        ]);
        $staff->departments()->attach($department);

        $user = User::factory()->create([
            'name' => $name,
            'user_type' => 'staff',
            'staff_id' => $staff->id,
            'active' => true,
        ]);

        if ($superAdmin) {
            $role = Role::query()->create([
                'name' => 'Super administrador',
                'slug' => 'super_admin',
                'active' => true,
            ]);
            $user->roles()->attach($role);
        }

        return $user;
    }

    private function createCatalogs(User $actor): array
    {
        $subject = CentroApuntesAsignatura::query()->create([
            'name' => 'Matemática',
            'code' => 'MAT-'.uniqid(),
            'area' => 'Matemática',
            'education_level' => '1° a 4° Medio',
            'status' => 'activa',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $machine = CentroApuntesMaquina::query()->create([
            'name' => 'Multifuncional principal',
            'internal_code' => 'MFP-'.uniqid(),
            'type' => 'multifuncional',
            'status' => 'activa',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return [$subject, $machine];
    }

    private function createRequest(
        User $requester,
        Department $department,
        CentroApuntesAsignatura $subject,
        CentroApuntesMaquina $machine,
        array $overrides,
    ): CentroApuntesSolicitud {
        return CentroApuntesSolicitud::query()->create(array_merge([
            'request_code' => 'CAP-'.uniqid(),
            'requested_by_user_id' => $requester->id,
            'requested_by_name_snapshot' => $requester->name,
            'department_id' => $department->id,
            'department_name_snapshot' => $department->name,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'machine_id' => $machine->id,
            'machine_name_snapshot' => $machine->name,
            'task_type' => 'guia',
            'requested_at' => now(),
            'delivery_date' => now()->addDay()->toDateString(),
            'sheet_count' => 1,
            'copies_count' => 1,
            'paper_size' => 'carta',
            'priority' => 'normal',
            'is_urgent' => false,
            'is_immediate' => false,
            'status' => 'pendiente',
            'estimated_total_impressions' => 1,
            'status_changed_at' => now(),
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
        ], $overrides));
    }
}
