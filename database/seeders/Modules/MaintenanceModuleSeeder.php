<?php

namespace Database\Seeders\Modules;

use App\Models\MaintenanceAnnualPlan;
use App\Models\InventoryItem;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceVisit;
use App\Models\MaintenanceVisitChecklistResponse;
use App\Models\MaintenanceWorkOrder;
use Database\Seeders\Support\ModuleSeeder;

class MaintenanceModuleSeeder extends ModuleSeeder
{
    public function run(): void
    {
        $workOrders = $this->seedWorkOrders();
        $this->seedVisitsAndChecklist($workOrders);
        $this->seedAnnualPlan();
    }

    /**
     * @return array<string, \App\Models\MaintenanceWorkOrder>
     */
    private function seedWorkOrders(): array
    {
        $definitions = [
            'seed-wo-001' => [
                'dependency' => 'DEP-003',
                'reported_at' => '2026-06-18',
                'requested_by' => 'Patricia López Herrera',
                'assigned_to' => 'Ricardo Fuentes Leal',
                'priority' => 'Crítico',
                'status' => 'En proceso',
                'due_date' => '2026-06-28',
                'description' => 'Intermitencia eléctrica en tres puestos del laboratorio de computación.',
                'resolution_notes' => 'Pendiente revisión de circuito y reemplazo de tomas defectuosos.',
            ],
            'seed-wo-002' => [
                'dependency' => 'DEP-007',
                'reported_at' => '2026-06-21',
                'requested_by' => 'Sergio Torres Álvarez',
                'assigned_to' => 'Ricardo Fuentes Leal',
                'priority' => 'Alta',
                'status' => 'En espera',
                'due_date' => '2026-07-05',
                'description' => 'Filtración menor en cubierta lateral del gimnasio.',
                'resolution_notes' => 'Esperando presupuesto de sellado y reemplazo de plancha.',
            ],
            'seed-wo-003' => [
                'dependency' => 'DEP-010',
                'reported_at' => '2026-06-08',
                'requested_by' => 'Nicolás Pérez Salazar',
                'assigned_to' => 'Ricardo Fuentes Leal',
                'priority' => 'Baja',
                'status' => 'Terminado',
                'due_date' => '2026-06-10',
                'description' => 'Mantención preventiva de extintores y señalética del auditorio.',
                'resolution_notes' => 'Checklist completado y extintor recargado.',
            ],
            'seed-wo-004' => [
                'dependency' => 'DEP-014',
                'reported_at' => '2026-06-25',
                'requested_by' => 'Ivonne Reyes Gallardo',
                'assigned_to' => 'Ricardo Fuentes Leal',
                'priority' => 'Media',
                'status' => 'Sin comenzar',
                'due_date' => '2026-07-15',
                'description' => 'Instalación de nueva repisa para insumos clínicos en enfermería.',
            ],
            'seed-wo-005' => [
                'dependency' => 'DEP-018',
                'reported_at' => '2026-06-14',
                'requested_by' => 'Paula Vargas Molina',
                'assigned_to' => 'Ricardo Fuentes Leal',
                'priority' => 'Media',
                'status' => 'Pausado',
                'due_date' => '2026-07-12',
                'description' => 'Actualización de luminarias y canalización de red en CRA.',
                'resolution_notes' => 'Trabajo pausado por espera de repuestos.',
            ],
            'seed-wo-006' => [
                'dependency' => 'DEP-003',
                'reported_at' => '2026-06-17',
                'requested_by' => 'Ricardo Fuentes Leal',
                'assigned_to' => 'Ricardo Fuentes Leal',
                'priority' => 'Alta',
                'status' => 'Sin comenzar',
                'due_date' => '2026-06-30',
                'description' => 'Reemplazo de enchufes y canaleta dañada en muro norte del laboratorio.',
                'resolution_notes' => 'Originada desde checklist de visita preventiva.',
            ],
        ];

        $records = [];

        foreach ($definitions as $sourceKey => $definition) {
            $dependency = $this->dependency($definition['dependency']);

            $records[$sourceKey] = MaintenanceWorkOrder::query()->updateOrCreate(
                ['source_key' => $sourceKey],
                [
                    'maintenance_dependency_id' => $dependency->id,
                    'location_code' => $dependency->code,
                    'location_distribution' => $dependency->distribution,
                    'location_sector' => $dependency->sector,
                    'location_name' => $dependency->name,
                    'location_usage' => $dependency->usage,
                    'reported_at' => $definition['reported_at'],
                    'requested_by' => $definition['requested_by'],
                    'assigned_to' => $definition['assigned_to'],
                    'priority' => $definition['priority'],
                    'status' => $definition['status'],
                    'due_date' => $definition['due_date'] ?? null,
                    'description' => $definition['description'],
                    'resolution_notes' => $definition['resolution_notes'] ?? null,
                ],
            );
        }

        return $records;
    }

    /**
     * @param  array<string, \App\Models\MaintenanceWorkOrder>  $workOrders
     */
    private function seedVisitsAndChecklist(array $workOrders): void
    {
        $definitions = [
            [
                'key' => ['maintenance_dependency_id' => $this->dependency('DEP-003')->id, 'visit_date' => '2026-06-17', 'responsible' => 'Ricardo Fuentes Leal'],
                'visit_time' => '08:30',
                'visit_type' => 'Inspección',
                'status' => 'Finalizada',
                'notes' => 'Revisión preventiva del laboratorio previa a evaluaciones semestrales.',
                'responses' => [
                    ['index' => 0, 'review_status' => 'OK', 'observations' => 'Muros y cielo en buen estado.'],
                    ['index' => 1, 'review_status' => 'No OK', 'observations' => 'Dos enchufes con falso contacto.', 'finding_description' => 'Se detecta canaleta quebrada y tres tomas dañadas en muro norte.', 'work_order' => 'seed-wo-006'],
                    ['index' => 2, 'review_status' => 'OK', 'observations' => 'Señalética visible y extintor vigente.'],
                ],
            ],
            [
                'key' => ['maintenance_dependency_id' => $this->dependency('DEP-007')->id, 'visit_date' => '2026-06-24', 'responsible' => 'Ricardo Fuentes Leal'],
                'visit_time' => '09:00',
                'visit_type' => 'Mantención',
                'status' => 'En progreso',
                'notes' => 'Levantamiento en cubierta lateral y evaluación de filtración.',
                'responses' => [
                    ['index' => 0, 'review_status' => 'No OK', 'observations' => 'Humedad visible en un costado de la cubierta.', 'finding_description' => 'Posible ingreso de agua por unión de planchas.'],
                ],
            ],
            [
                'key' => ['maintenance_dependency_id' => $this->dependency('DEP-010')->id, 'visit_date' => '2026-07-05', 'responsible' => 'Nicolás Pérez Salazar'],
                'visit_time' => '10:00',
                'visit_type' => 'Inspección',
                'status' => 'Programada',
                'notes' => 'Inspección mensual de seguridad del auditorio y evacuación.',
            ],
            [
                'key' => ['maintenance_dependency_id' => $this->dependency('DEP-014')->id, 'visit_date' => '2026-07-08', 'responsible' => 'Ivonne Reyes Gallardo'],
                'visit_time' => '11:30',
                'visit_type' => 'Reunión',
                'status' => 'Programada',
                'notes' => 'Coordinación de mejoras de almacenamiento en enfermería.',
            ],
        ];

        $items = $this->ensureChecklistItems()->values();

        foreach ($definitions as $definition) {
            $visit = MaintenanceVisit::query()->updateOrCreate(
                $definition['key'],
                [
                    'visit_time' => $definition['visit_time'] ?? null,
                    'visit_type' => $definition['visit_type'],
                    'status' => $definition['status'],
                    'notes' => $definition['notes'] ?? null,
                ],
            );

            foreach ($definition['responses'] ?? [] as $response) {
                $item = $items[$response['index']] ?? $items->first();

                if (!$item) {
                    continue;
                }

                MaintenanceVisitChecklistResponse::query()->updateOrCreate(
                    [
                        'maintenance_visit_id' => $visit->id,
                        'maintenance_checklist_item_id' => $item->id,
                    ],
                    [
                        'review_status' => $response['review_status'] ?? null,
                        'observations' => $response['observations'] ?? null,
                        'finding_description' => $response['finding_description'] ?? null,
                        'work_order_id' => isset($response['work_order']) ? $workOrders[$response['work_order']]->id : null,
                    ],
                );
            }
        }
    }

    private function seedAnnualPlan(): void
    {
        $technicalArea = $this->ensureAnnualPlanTechnicalArea();

        $plans = [
            [
                'title' => 'Revisión tablero eléctrico laboratorio',
                'dependency' => 'DEP-003',
                'item_type' => 'dependency_component',
                'component_name' => 'Tablero eléctrico',
                'planned_year' => 2026,
                'planned_month' => 7,
                'category' => 'Eléctrica',
                'responsible' => 'Ricardo Fuentes Leal',
                'frequency' => 'Mensual',
                'status' => 'Programada',
                'scheduled_date' => '2026-07-12',
                'description' => 'Chequeo preventivo de tablero, enchufes y canalización.',
            ],
            [
                'title' => 'Mantención anual extintor CO2 auditorio',
                'dependency' => 'DEP-010',
                'item_type' => 'inventory_item',
                'inventory_code' => 'SEG-0001',
                'planned_year' => 2026,
                'planned_month' => 8,
                'category' => 'Extintores',
                'responsible' => 'Nicolás Pérez Salazar',
                'frequency' => 'Anual',
                'status' => 'Programada',
                'scheduled_date' => '2026-08-20',
                'last_maintenance_date' => '2025-08-20',
                'alert_days' => 45,
                'description' => 'Recarga, revisión de sello, manómetro, ubicación y señalética.',
            ],
            [
                'title' => 'Mantención cubierta gimnasio',
                'dependency' => 'DEP-007',
                'item_type' => 'dependency_component',
                'component_name' => 'Cubierta',
                'planned_year' => 2026,
                'planned_month' => 7,
                'category' => 'Infraestructura',
                'responsible' => 'Ricardo Fuentes Leal',
                'frequency' => 'Anual',
                'status' => 'En ejecución',
                'scheduled_date' => '2026-07-04',
                'description' => 'Sellado y ajuste de planchas en borde poniente.',
            ],
            [
                'title' => 'Control sanitario enfermería',
                'dependency' => 'DEP-014',
                'item_type' => 'dependency',
                'planned_year' => 2026,
                'planned_month' => 8,
                'category' => 'General',
                'responsible' => 'Ivonne Reyes Gallardo',
                'frequency' => 'Mensual',
                'status' => 'Programada',
                'scheduled_date' => '2026-08-05',
                'description' => 'Orden de insumos, revisión de equipos y trazabilidad.',
            ],
            [
                'title' => 'Limpieza profunda CRA y biblioteca',
                'dependency' => 'DEP-018',
                'item_type' => 'dependency',
                'planned_year' => 2026,
                'planned_month' => 6,
                'category' => 'Aseo',
                'responsible' => 'Patricia López Herrera',
                'frequency' => 'Semanal',
                'status' => 'Vencida',
                'scheduled_date' => '2026-06-06',
                'description' => 'Rutina intensiva de limpieza y sanitización de estanterías.',
                'notes' => 'Pendiente reprogramación por actividad institucional.',
            ],
            [
                'title' => 'Revisión ventanas biblioteca',
                'dependency' => 'DEP-018',
                'item_type' => 'dependency_component',
                'component_name' => 'Ventanas',
                'planned_year' => 2026,
                'planned_month' => 7,
                'category' => 'Elementos constructivos',
                'responsible' => 'Ricardo Fuentes Leal',
                'frequency' => 'Semestral',
                'status' => 'Programada',
                'scheduled_date' => '2026-07-22',
                'last_maintenance_date' => '2026-01-22',
                'alert_days' => 20,
                'description' => 'Revisión de cierres, vidrios, sellos y apertura segura.',
            ],
            [
                'title' => 'Mantención caja de redes biblioteca',
                'dependency' => 'DEP-018',
                'item_type' => 'technical_area',
                'technical_area_id' => $technicalArea->id,
                'planned_year' => 2026,
                'planned_month' => 9,
                'category' => 'Redes/Informática',
                'responsible' => 'Ricardo Fuentes Leal',
                'frequency' => 'Semestral',
                'status' => 'Programada',
                'scheduled_date' => '2026-09-10',
                'last_maintenance_date' => '2026-03-10',
                'alert_days' => 30,
                'description' => 'Ordenamiento, limpieza, revisión de patch cords y etiquetado.',
            ],
        ];

        foreach ($plans as $plan) {
            $dependency = $this->dependency($plan['dependency']);
            $inventoryItem = isset($plan['inventory_code'])
                ? InventoryItem::query()->where('code', $plan['inventory_code'])->first()
                : null;
            $itemType = $plan['item_type'] ?? 'dependency';
            $resolvedItemType = $inventoryItem ? $itemType : ($itemType === 'inventory_item' ? 'dependency_component' : $itemType);

            MaintenanceAnnualPlan::query()->updateOrCreate(
                [
                    'maintenance_dependency_id' => $dependency->id,
                    'planned_year' => $plan['planned_year'],
                    'planned_month' => $plan['planned_month'],
                    'title' => $plan['title'],
                ],
                [
                    'item_type' => $resolvedItemType,
                    'inventory_item_id' => $inventoryItem?->id,
                    'technical_area_id' => $resolvedItemType === 'technical_area' ? ($plan['technical_area_id'] ?? null) : null,
                    'component_name' => $resolvedItemType === 'dependency_component'
                        ? ($plan['component_name'] ?? $plan['title'])
                        : null,
                    'category' => $plan['category'],
                    'responsible' => $plan['responsible'],
                    'frequency' => $plan['frequency'],
                    'status' => $plan['status'],
                    'description' => $plan['description'] ?? null,
                    'scheduled_date' => $plan['scheduled_date'] ?? null,
                    'completed_date' => $plan['completed_date'] ?? null,
                    'last_maintenance_date' => $plan['last_maintenance_date'] ?? $plan['completed_date'] ?? null,
                    'alert_days' => $plan['alert_days'] ?? 30,
                    'alert_enabled' => $plan['alert_enabled'] ?? true,
                    'notes' => $plan['notes'] ?? null,
                ],
            );
        }
    }

    private function ensureAnnualPlanTechnicalArea(): MaintenanceDependency
    {
        $parent = $this->dependency('DEP-018');

        return MaintenanceDependency::query()->updateOrCreate(
            ['code' => 'AT-BIB-RED-01'],
            [
                'dependency_kind' => MaintenanceDependency::KIND_TECHNICAL_ASSET,
                'parent_dependency_id' => $parent->id,
                'name' => 'Caja de redes biblioteca',
                'description' => 'Punto técnico para red y conectividad de biblioteca.',
                'location' => $parent->location,
                'floor_sector' => $parent->floor_sector,
                'distribution' => $parent->distribution ?: 'Sector CRA',
                'sector' => $parent->sector ?: 'Primer piso',
                'zone' => 'Zona técnica',
                'usage' => 'Redes',
                'distribution_code' => 'AT',
                'floor_code' => 'BIB',
                'dependency_code' => 'RED',
                'numbering' => 1,
                'active' => true,
                'calendar_color' => '#5b74df',
                'requires_approval' => false,
                'is_reservable' => false,
                'is_inventory_auditable' => false,
                'is_maintenance_location' => false,
            ],
        );
    }
}
