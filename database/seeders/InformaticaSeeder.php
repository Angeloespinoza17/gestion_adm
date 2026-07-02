<?php

namespace Database\Seeders;

use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentAttachment;
use App\Models\It\ItEquipmentLoan;
use App\Models\It\ItEquipmentMaintenanceReport;
use App\Models\It\ItEquipmentStatusLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Informatica\InformaticaAccessService;
use App\Services\Informatica\ItEquipmentLoanService;
use App\Services\Informatica\ItEquipmentMaintenanceService;
use App\Services\Informatica\ItEquipmentService;
use Carbon\Carbon;
use Database\Seeders\Modules\StaffModuleSeeder;
use Database\Seeders\Modules\StudentModuleSeeder;
use Illuminate\Database\Seeder;

class InformaticaSeeder extends Seeder
{
    private Carbon $now;

    private User $actor;

    private InformaticaAccessService $accessService;

    private ItEquipmentService $equipmentService;

    private ItEquipmentLoanService $loanService;

    private ItEquipmentMaintenanceService $maintenanceService;

    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            ChileLocationSeeder::class,
            SchoolDepartmentSeeder::class,
            StudentModuleSeeder::class,
            StaffModuleSeeder::class,
        ]);

        $this->now = Carbon::parse('2026-06-30 10:30:00');
        Carbon::setTestNow($this->now);

        $this->accessService = app(InformaticaAccessService::class);
        $this->equipmentService = app(ItEquipmentService::class);
        $this->loanService = app(ItEquipmentLoanService::class);
        $this->maintenanceService = app(ItEquipmentMaintenanceService::class);
        $this->actor = User::query()->where('email', 'superadmin@cnscgestion.cl')->firstOrFail();

        $this->seedPermissionsAndModules();
        $this->resetSeededData();

        $equipment = $this->seedEquipment();
        $this->seedLoans($equipment);
        $this->seedMaintenance($equipment);
        $this->loanService->refreshOverdueStatuses();

        Carbon::setTestNow();
    }

    private function seedPermissionsAndModules(): void
    {
        $permissions = $this->accessService->permissionDefinitions();

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => 'Permiso del módulo de Informática.',
                    'active' => true,
                ]
            );
        }

        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'informatica'],
            [
                'name' => 'Informática',
                'frontend_route' => null,
                'icon' => 'bx-laptop',
                'sort_order' => 86,
                'active' => true,
                'parent_id' => null,
            ]
        );

        $children = [
            ['slug' => 'informatica_dashboard', 'name' => 'Dashboard', 'route' => '/informatica', 'sort' => 1],
            ['slug' => 'informatica_equipos', 'name' => 'Equipos', 'route' => '/informatica/equipos', 'sort' => 2],
            ['slug' => 'informatica_prestamos', 'name' => 'Préstamos', 'route' => '/informatica/prestamos', 'sort' => 3],
            ['slug' => 'informatica_mantenciones', 'name' => 'Mantenciones', 'route' => '/informatica/mantenciones', 'sort' => 4],
            ['slug' => 'informatica_reportes', 'name' => 'Reportes', 'route' => '/informatica/reportes', 'sort' => 5],
        ];

        foreach ($children as $child) {
            SystemModule::query()->updateOrCreate(
                ['slug' => $child['slug']],
                [
                    'name' => $child['name'],
                    'frontend_route' => $child['route'],
                    'icon' => null,
                    'sort_order' => $child['sort'],
                    'active' => true,
                    'parent_id' => $parent->id,
                ]
            );
        }

        $permissionsBySlug = Permission::query()
            ->whereIn('slug', array_column($permissions, 'slug'))
            ->get()
            ->keyBy('slug');

        $modules = SystemModule::query()
            ->whereIn('slug', array_merge(['informatica'], array_column($children, 'slug')))
            ->get()
            ->keyBy('slug');

        $rolePermissionMap = [
            'super_admin' => array_keys($permissionsBySlug->all()),
            'administrador' => array_keys($permissionsBySlug->all()),
            'encargado_mantencion' => array_keys($permissionsBySlug->all()),
            'administrativo' => [
                InformaticaAccessService::VIEW_PERMISSION,
                InformaticaAccessService::DASHBOARD_PERMISSION,
                InformaticaAccessService::EQUIPMENT_VIEW_PERMISSION,
                InformaticaAccessService::LOANS_VIEW_PERMISSION,
                InformaticaAccessService::MAINTENANCE_VIEW_PERMISSION,
                InformaticaAccessService::REPORTS_VIEW_PERMISSION,
            ],
            'inspectoria' => [
                InformaticaAccessService::VIEW_PERMISSION,
                InformaticaAccessService::DASHBOARD_PERMISSION,
                InformaticaAccessService::LOANS_VIEW_PERMISSION,
                InformaticaAccessService::LOANS_CREATE_PERMISSION,
                InformaticaAccessService::LOANS_RETURN_PERMISSION,
                InformaticaAccessService::LOANS_CANCEL_PERMISSION,
            ],
            'docente' => [
                InformaticaAccessService::VIEW_PERMISSION,
                InformaticaAccessService::DASHBOARD_PERMISSION,
                InformaticaAccessService::LOANS_VIEW_PERMISSION,
            ],
        ];

        $roleModuleMap = [
            'super_admin' => $modules->keys()->all(),
            'administrador' => $modules->keys()->all(),
            'encargado_mantencion' => $modules->keys()->all(),
            'administrativo' => ['informatica', 'informatica_dashboard', 'informatica_equipos', 'informatica_prestamos', 'informatica_mantenciones', 'informatica_reportes'],
            'inspectoria' => ['informatica', 'informatica_dashboard', 'informatica_prestamos'],
            'docente' => ['informatica', 'informatica_dashboard', 'informatica_prestamos'],
        ];

        foreach ($rolePermissionMap as $roleSlug => $permissionSlugs) {
            $role = Role::query()->firstWhere('slug', $roleSlug);

            if (!$role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching(
                collect($permissionSlugs)
                    ->map(fn (string $slug) => $permissionsBySlug[$slug]?->id)
                    ->filter()
                    ->all()
            );

            $role->modules()->syncWithoutDetaching(
                collect($roleModuleMap[$roleSlug] ?? [])
                    ->map(fn (string $slug) => $modules[$slug]?->id)
                    ->filter()
                    ->all()
            );
        }
    }

    private function resetSeededData(): void
    {
        $equipmentIds = ItEquipment::query()
            ->withTrashed()
            ->where('internal_code', 'like', 'INF-IT-SEED-%')
            ->pluck('id');

        if ($equipmentIds->isNotEmpty()) {
            ItEquipmentAttachment::query()->withTrashed()->whereIn('it_equipment_id', $equipmentIds)->forceDelete();
            ItEquipmentStatusLog::query()->whereIn('it_equipment_id', $equipmentIds)->delete();
            ItEquipmentLoan::query()->withTrashed()->whereIn('it_equipment_id', $equipmentIds)->forceDelete();
            ItEquipmentMaintenanceReport::query()->withTrashed()->whereIn('it_equipment_id', $equipmentIds)->forceDelete();
            ItEquipment::query()->withTrashed()->whereIn('id', $equipmentIds)->forceDelete();
        }

        ItEquipmentLoan::query()->withTrashed()->where('loan_code', 'like', 'INF-PRE-SEED-%')->forceDelete();
        ItEquipmentMaintenanceReport::query()->withTrashed()->where('maintenance_code', 'like', 'INF-MAN-SEED-%')->forceDelete();
    }

    /**
     * @return array<string, ItEquipment>
     */
    private function seedEquipment(): array
    {
        $definitions = [
            [
                'code' => 'INF-IT-SEED-001',
                'equipment_type' => 'notebook',
                'brand' => 'Lenovo',
                'model' => 'ThinkBook 14 G6',
                'serial_number' => 'INF-IT-SN-001',
                'location_name' => 'Sala Enlaces',
                'responsible_user_id' => $this->actor->id,
                'acquisition_date' => '2025-03-12',
                'reference_value' => 749990,
                'observations' => 'Equipo para soporte docente.',
            ],
            [
                'code' => 'INF-IT-SEED-002',
                'equipment_type' => 'notebook',
                'brand' => 'HP',
                'model' => 'ProBook 440',
                'serial_number' => 'INF-IT-SN-002',
                'location_name' => 'Biblioteca',
                'responsible_name' => 'Coordinación académica',
                'acquisition_date' => '2024-09-02',
                'reference_value' => 689990,
                'observations' => 'Notebook de apoyo CRA.',
            ],
            [
                'code' => 'INF-IT-SEED-003',
                'equipment_type' => 'desktop',
                'brand' => 'Dell',
                'model' => 'OptiPlex 7010',
                'serial_number' => 'INF-IT-SN-003',
                'location_name' => 'Laboratorio 1',
                'responsible_name' => 'Laboratorio de computación',
                'acquisition_date' => '2023-04-18',
                'reference_value' => 559990,
            ],
            [
                'code' => 'INF-IT-SEED-004',
                'equipment_type' => 'projector',
                'brand' => 'Epson',
                'model' => 'PowerLite X49',
                'serial_number' => 'INF-IT-SN-004',
                'location_name' => 'Bodega audiovisual',
                'responsible_name' => 'Inspectoría',
                'acquisition_date' => '2022-08-23',
                'reference_value' => 449990,
            ],
            [
                'code' => 'INF-IT-SEED-005',
                'equipment_type' => 'tablet',
                'brand' => 'Samsung',
                'model' => 'Galaxy Tab A9',
                'serial_number' => 'INF-IT-SN-005',
                'location_name' => 'Programa PIE',
                'responsible_name' => 'PIE',
                'acquisition_date' => '2025-01-15',
                'reference_value' => 259990,
            ],
            [
                'code' => 'INF-IT-SEED-006',
                'equipment_type' => 'printer',
                'brand' => 'Brother',
                'model' => 'DCP-T720DW',
                'serial_number' => 'INF-IT-SN-006',
                'location_name' => 'Secretaría',
                'responsible_name' => 'Secretaría académica',
                'acquisition_date' => '2021-11-03',
                'reference_value' => 329990,
            ],
            [
                'code' => 'INF-IT-SEED-007',
                'equipment_type' => 'router',
                'brand' => 'MikroTik',
                'model' => 'hAP ax2',
                'serial_number' => 'INF-IT-SN-007',
                'location_name' => 'Sala de servidores',
                'responsible_user_id' => $this->actor->id,
                'acquisition_date' => '2024-04-10',
                'reference_value' => 189990,
            ],
            [
                'code' => 'INF-IT-SEED-008',
                'equipment_type' => 'monitor',
                'brand' => 'LG',
                'model' => '24MP400',
                'serial_number' => 'INF-IT-SN-008',
                'location_name' => 'Laboratorio 2',
                'responsible_name' => 'Laboratorio de computación',
                'acquisition_date' => '2023-10-05',
                'reference_value' => 144990,
            ],
            [
                'code' => 'INF-IT-SEED-009',
                'equipment_type' => 'switch',
                'brand' => 'TP-Link',
                'model' => 'TL-SG1024',
                'serial_number' => 'INF-IT-SN-009',
                'location_name' => 'Sala de servidores',
                'responsible_user_id' => $this->actor->id,
                'acquisition_date' => '2019-06-21',
                'reference_value' => 214990,
            ],
            [
                'code' => 'INF-IT-SEED-010',
                'equipment_type' => 'camera',
                'brand' => 'Logitech',
                'model' => 'Brio 500',
                'serial_number' => 'INF-IT-SN-010',
                'location_name' => 'Sala multimedia',
                'responsible_name' => 'Comunicaciones',
                'acquisition_date' => '2025-02-20',
                'reference_value' => 139990,
            ],
            [
                'code' => 'INF-IT-SEED-011',
                'equipment_type' => 'speaker',
                'brand' => 'JBL',
                'model' => 'PartyBox 110',
                'serial_number' => 'INF-IT-SN-011',
                'location_name' => 'Aula magna',
                'responsible_name' => 'Actos y eventos',
                'acquisition_date' => '2024-12-18',
                'reference_value' => 349990,
            ],
            [
                'code' => 'INF-IT-SEED-012',
                'equipment_type' => 'notebook',
                'brand' => 'Acer',
                'model' => 'TravelMate P2',
                'serial_number' => 'INF-IT-SN-012',
                'location_name' => 'Coordinación PIE',
                'responsible_name' => 'PIE',
                'acquisition_date' => '2023-03-08',
                'reference_value' => 619990,
            ],
        ];

        $equipment = [];

        foreach ($definitions as $definition) {
            $item = $this->equipmentService->create([
                'internal_code' => $definition['code'],
                'equipment_type' => $definition['equipment_type'],
                'brand' => $definition['brand'],
                'model' => $definition['model'],
                'serial_number' => $definition['serial_number'],
                'status' => 'disponible',
                'location_name' => $definition['location_name'],
                'responsible_user_id' => $definition['responsible_user_id'] ?? null,
                'responsible_name' => $definition['responsible_name'] ?? null,
                'acquisition_date' => $definition['acquisition_date'],
                'reference_value' => $definition['reference_value'],
                'observations' => $definition['observations'] ?? null,
                'active' => true,
            ], $this->actor);

            $equipment[$definition['code']] = $item;
        }

        return $equipment;
    }

    /**
     * @param  array<string, ItEquipment>  $equipment
     */
    private function seedLoans(array $equipment): void
    {
        $activeLoan = $this->loanService->create([
            'loan_code' => 'INF-PRE-SEED-001',
            'it_equipment_id' => $equipment['INF-IT-SEED-003']->id,
            'requester_type' => 'funcionario',
            'requester_name' => 'María Soledad Torres',
            'requester_rut' => '17.123.456-7',
            'requester_contact' => 'maria.torres@colegio.test',
            'borrowed_at' => $this->now->copy()->subDays(1)->format('Y-m-d H:i:s'),
            'due_at' => $this->now->copy()->addDays(4)->format('Y-m-d H:i:s'),
            'purpose' => 'Uso de software administrativo.',
            'location_name' => 'Dirección académica',
            'delivered_by_user_id' => $this->actor->id,
            'notes' => 'Préstamo operativo vigente.',
        ], $this->actor);

        $overdueLoan = $this->loanService->create([
            'loan_code' => 'INF-PRE-SEED-002',
            'it_equipment_id' => $equipment['INF-IT-SEED-004']->id,
            'requester_type' => 'estudiante',
            'requester_name' => 'Vicente Gallardo',
            'requester_rut' => '22.456.789-0',
            'requester_contact' => '+56987654321',
            'borrowed_at' => $this->now->copy()->subDays(6)->format('Y-m-d H:i:s'),
            'due_at' => $this->now->copy()->subDays(2)->format('Y-m-d H:i:s'),
            'purpose' => 'Presentación de ciencias.',
            'location_name' => 'Sala 2 medio A',
            'delivered_by_user_id' => $this->actor->id,
            'notes' => 'Préstamo que debe quedar atrasado.',
        ], $this->actor);

        $returnedGoodLoan = $this->loanService->create([
            'loan_code' => 'INF-PRE-SEED-003',
            'it_equipment_id' => $equipment['INF-IT-SEED-002']->id,
            'requester_type' => 'externo',
            'requester_name' => 'Capacitación Docente Externa',
            'requester_contact' => 'capacitacion@example.test',
            'borrowed_at' => $this->now->copy()->subDays(8)->format('Y-m-d H:i:s'),
            'due_at' => $this->now->copy()->subDays(6)->format('Y-m-d H:i:s'),
            'purpose' => 'Jornada de formación.',
            'location_name' => 'Salón de actos',
            'delivered_by_user_id' => $this->actor->id,
            'notes' => 'Préstamo devuelto en buen estado.',
        ], $this->actor);

        $this->loanService->registerReturn($returnedGoodLoan, [
            'returned_at' => $this->now->copy()->subDays(5)->format('Y-m-d H:i:s'),
            'received_by_user_id' => $this->actor->id,
            'return_condition' => 'bueno',
            'return_notes' => 'Se recibe conforme.',
        ], $this->actor);

        $returnedDamagedLoan = $this->loanService->create([
            'loan_code' => 'INF-PRE-SEED-004',
            'it_equipment_id' => $equipment['INF-IT-SEED-005']->id,
            'requester_type' => 'apoderado',
            'requester_name' => 'Claudia Martínez',
            'requester_rut' => '14.987.654-3',
            'requester_contact' => '+56999887766',
            'borrowed_at' => $this->now->copy()->subDays(10)->format('Y-m-d H:i:s'),
            'due_at' => $this->now->copy()->subDays(7)->format('Y-m-d H:i:s'),
            'purpose' => 'Apoyo pedagógico domiciliario.',
            'location_name' => 'Programa de inclusión',
            'delivered_by_user_id' => $this->actor->id,
            'notes' => 'Préstamo con devolución dañada.',
        ], $this->actor);

        $this->loanService->registerReturn($returnedDamagedLoan, [
            'returned_at' => $this->now->copy()->subDays(6)->format('Y-m-d H:i:s'),
            'received_by_user_id' => $this->actor->id,
            'return_condition' => 'danado',
            'return_notes' => 'Pantalla con golpe en esquina superior.',
        ], $this->actor);

        $cancelledLoan = $this->loanService->create([
            'loan_code' => 'INF-PRE-SEED-005',
            'it_equipment_id' => $equipment['INF-IT-SEED-011']->id,
            'requester_type' => 'otro',
            'requester_name' => 'Equipo de convivencia',
            'requester_contact' => 'convivencia@colegio.test',
            'borrowed_at' => $this->now->copy()->subDays(3)->format('Y-m-d H:i:s'),
            'due_at' => $this->now->copy()->addDays(3)->format('Y-m-d H:i:s'),
            'purpose' => 'Actividad suspendida por lluvia.',
            'location_name' => 'Patio techado',
            'delivered_by_user_id' => $this->actor->id,
            'notes' => 'Queda cancelado para mostrar el flujo.',
        ], $this->actor);

        $this->loanService->cancel($cancelledLoan, $this->actor, 'Actividad reprogramada.');

        $activeLoan->refresh();
        $overdueLoan->refresh();
    }

    /**
     * @param  array<string, ItEquipment>  $equipment
     */
    private function seedMaintenance(array $equipment): void
    {
        $this->maintenanceService->create([
            'maintenance_code' => 'INF-MAN-SEED-001',
            'it_equipment_id' => $equipment['INF-IT-SEED-006']->id,
            'maintenance_date' => $this->now->copy()->subDays(2)->format('Y-m-d'),
            'maintenance_type' => 'correctiva',
            'technician_user_id' => $this->actor->id,
            'reason' => 'Atasco de papel recurrente.',
            'diagnosis' => 'Rodillo de arrastre con desgaste.',
            'actions_performed' => 'Limpieza interna y revisión de bandeja.',
            'spare_parts' => 'Rodillo de arrastre compatible.',
            'cost_amount' => 38990,
            'initial_equipment_status' => 'disponible',
            'next_maintenance_at' => $this->now->copy()->addMonths(4)->format('Y-m-d'),
            'observations' => 'Pendiente de validación final del usuario.',
            'status' => 'finalizado',
        ], $this->actor);

        $routerReport = $this->maintenanceService->create([
            'maintenance_code' => 'INF-MAN-SEED-002',
            'it_equipment_id' => $equipment['INF-IT-SEED-007']->id,
            'maintenance_date' => $this->now->copy()->subDays(4)->format('Y-m-d'),
            'maintenance_type' => 'preventiva',
            'technician_user_id' => $this->actor->id,
            'reason' => 'Mantención programada de red.',
            'diagnosis' => 'Firmware desactualizado.',
            'actions_performed' => 'Actualización de firmware y respaldo de configuración.',
            'spare_parts' => null,
            'cost_amount' => 0,
            'initial_equipment_status' => 'disponible',
            'next_maintenance_at' => $this->now->copy()->addMonths(6)->format('Y-m-d'),
            'observations' => 'Equipo vuelve a operación normal.',
            'status' => 'pendiente_revision',
        ], $this->actor);

        $this->maintenanceService->close($routerReport, [
            'final_equipment_status' => 'disponible',
            'closed_at' => $this->now->copy()->subDays(3)->format('Y-m-d H:i:s'),
            'observations' => 'Mantención cerrada sin hallazgos críticos.',
        ], $this->actor);

        $monitorReport = $this->maintenanceService->create([
            'maintenance_code' => 'INF-MAN-SEED-003',
            'it_equipment_id' => $equipment['INF-IT-SEED-008']->id,
            'maintenance_date' => $this->now->copy()->subDays(7)->format('Y-m-d'),
            'maintenance_type' => 'diagnostico',
            'technician_user_id' => $this->actor->id,
            'reason' => 'Fallas intermitentes de imagen.',
            'diagnosis' => 'Panel LCD fisurado.',
            'actions_performed' => 'Inspección y cotización de reemplazo.',
            'spare_parts' => 'No aplica.',
            'cost_amount' => 22990,
            'initial_equipment_status' => 'disponible',
            'next_maintenance_at' => null,
            'observations' => 'No conviene reparar respecto al valor del equipo.',
            'status' => 'finalizado',
        ], $this->actor);

        $this->maintenanceService->close($monitorReport, [
            'final_equipment_status' => 'danado',
            'closed_at' => $this->now->copy()->subDays(6)->format('Y-m-d H:i:s'),
            'observations' => 'Se mantiene como dañado a la espera de definición presupuestaria.',
        ], $this->actor);

        $switchReport = $this->maintenanceService->create([
            'maintenance_code' => 'INF-MAN-SEED-004',
            'it_equipment_id' => $equipment['INF-IT-SEED-009']->id,
            'maintenance_date' => $this->now->copy()->subDays(12)->format('Y-m-d'),
            'maintenance_type' => 'reparacion',
            'technician_user_id' => $this->actor->id,
            'reason' => 'Cortes de enlace en múltiples puertos.',
            'diagnosis' => 'Daño de placa principal por sobretensión.',
            'actions_performed' => 'Evaluación técnica y descarte de reparación.',
            'spare_parts' => 'No aplica.',
            'cost_amount' => 45990,
            'initial_equipment_status' => 'disponible',
            'next_maintenance_at' => null,
            'observations' => 'Se recomienda baja por obsolescencia y costo.',
            'status' => 'pendiente_revision',
        ], $this->actor);

        $this->maintenanceService->close($switchReport, [
            'final_equipment_status' => 'dado_de_baja',
            'closed_at' => $this->now->copy()->subDays(11)->format('Y-m-d H:i:s'),
            'observations' => 'Se da de baja definitivamente.',
        ], $this->actor);

        $this->maintenanceService->create([
            'maintenance_code' => 'INF-MAN-SEED-005',
            'it_equipment_id' => $equipment['INF-IT-SEED-010']->id,
            'maintenance_date' => $this->now->copy()->subDay()->format('Y-m-d'),
            'maintenance_type' => 'limpieza',
            'technician_user_id' => $this->actor->id,
            'reason' => 'Limpieza preventiva programada.',
            'diagnosis' => 'Sin fallas.',
            'actions_performed' => 'Borrador de limpieza agendada.',
            'spare_parts' => null,
            'cost_amount' => 0,
            'initial_equipment_status' => 'disponible',
            'next_maintenance_at' => $this->now->copy()->addMonths(3)->format('Y-m-d'),
            'observations' => 'Borrador en espera de confirmación.',
            'status' => 'borrador',
        ], $this->actor);

        $this->maintenanceService->create([
            'maintenance_code' => 'INF-MAN-SEED-006',
            'it_equipment_id' => $equipment['INF-IT-SEED-012']->id,
            'maintenance_date' => $this->now->copy()->subDays(5)->format('Y-m-d'),
            'maintenance_type' => 'actualizacion',
            'technician_user_id' => $this->actor->id,
            'reason' => 'Actualización de sistema operativo.',
            'diagnosis' => 'Disco con lentitud y espacio crítico.',
            'actions_performed' => 'Actualización, respaldo y limpieza de software.',
            'spare_parts' => 'Unidad SSD 512GB.',
            'cost_amount' => 89990,
            'initial_equipment_status' => 'disponible',
            'next_maintenance_at' => $this->now->copy()->addMonths(5)->format('Y-m-d'),
            'observations' => 'Pendiente validación con PIE.',
            'status' => 'pendiente_revision',
        ], $this->actor);
    }
}
