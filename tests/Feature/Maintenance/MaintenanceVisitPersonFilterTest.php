<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceDependency;
use App\Models\MaintenanceVisit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceVisitPersonFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_filters_by_staff_id_including_legacy_rows_and_new_visits_keep_the_staff_link(): void
    {
        $dependency = MaintenanceDependency::query()->create([
            'dependency_kind' => MaintenanceDependency::KIND_SPACE,
            'code' => 'DEP-CAL-01',
            'name' => 'Edificio calendario',
            'active' => true,
            'is_maintenance_location' => true,
        ]);
        $selectedStaff = $this->createStaff('Responsable Calendario', '11.111.111-1');
        $otherStaff = $this->createStaff('Responsable Alternativo', '22.222.222-2');

        $linkedVisit = $this->createVisit($dependency, [
            'responsible' => $selectedStaff->full_name,
            'responsible_staff_id' => $selectedStaff->id,
            'visit_date' => '2026-09-04',
        ]);
        $legacyVisit = $this->createVisit($dependency, [
            'responsible' => $selectedStaff->full_name,
            'responsible_staff_id' => null,
            'visit_date' => '2026-09-11',
        ]);
        $this->createVisit($dependency, [
            'responsible' => $otherStaff->full_name,
            'responsible_staff_id' => $otherStaff->id,
            'visit_date' => '2026-09-18',
        ]);

        Sanctum::actingAs($this->authorizedUser());
        $countBefore = MaintenanceVisit::count();

        $response = $this->getJson('/api/maintenance/visits?'.http_build_query([
            'responsible_staff_id' => $selectedStaff->id,
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'per_page' => 1000,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('status_totals.Programada', 2)
            ->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(
            [$linkedVisit->id, $legacyVisit->id],
            collect($response->json('data'))->pluck('id')->all()
        );
        $this->assertSame($countBefore, MaintenanceVisit::count());

        $created = $this->postJson('/api/maintenance/visits', [
            'maintenance_dependency_id' => $dependency->id,
            'responsible' => $selectedStaff->full_name,
            'visit_date' => '2026-09-25',
            'visit_time' => '09:30',
            'visit_type' => 'Inspección',
            'status' => 'Programada',
            'notes' => 'Visita creada desde el planificador.',
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('data.responsible_staff_id', $selectedStaff->id);
        $this->assertDatabaseHas('maintenance_visits', [
            'id' => $created->json('data.id'),
            'responsible_staff_id' => $selectedStaff->id,
            'responsible' => $selectedStaff->full_name,
        ]);
    }

    private function createStaff(string $name, string $rut): Staff
    {
        return Staff::query()->create([
            'full_name' => $name,
            'rut' => $rut,
            'active' => true,
            'can_receive_maintenance_orders' => true,
            'maintenance_role' => 'encargado_mantencion',
        ]);
    }

    private function createVisit(MaintenanceDependency $dependency, array $attributes): MaintenanceVisit
    {
        return MaintenanceVisit::query()->create([
            'maintenance_dependency_id' => $dependency->id,
            'responsible' => $attributes['responsible'],
            'responsible_staff_id' => $attributes['responsible_staff_id'],
            'visit_date' => $attributes['visit_date'],
            'visit_time' => '09:00',
            'visit_type' => 'Inspección',
            'status' => 'Programada',
        ]);
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create([
            'name' => 'Gestor calendario mantención',
            'slug' => 'gestor-calendario-mantencion-test',
            'active' => true,
        ]);

        foreach (['ver_visitas_mantencion', 'gestionar_visitas_mantencion'] as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'active' => true]
            );
            $role->permissions()->attach($permission);
        }

        $user->roles()->attach($role);

        return $user;
    }
}
