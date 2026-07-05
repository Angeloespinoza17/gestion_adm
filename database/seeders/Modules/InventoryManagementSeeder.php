<?php

namespace Database\Seeders\Modules;

use App\Models\InventoryDependencyAudit;
use App\Models\InventoryItem;
use Database\Seeders\Support\ModuleSeeder;

class InventoryManagementSeeder extends ModuleSeeder
{
    public function run(): void
    {
        $audits = [
            [
                'dependency' => 'DEP-003',
                'audited_at' => '2026-06-28 10:15:00',
                'found_items_count' => null,
                'notes' => 'Inventario de laboratorio revisado con equipos disponibles en sala.',
                'audited_by' => 'patricia.lopez@cnscgestion.local',
            ],
            [
                'dependency' => 'DEP-010',
                'audited_at' => '2026-06-20 16:40:00',
                'found_items_count' => null,
                'notes' => 'Se detectan bienes críticos pendientes de mantención.',
                'audited_by' => 'nicolas.perez@cnscgestion.local',
            ],
            [
                'dependency' => 'DEP-019',
                'audited_at' => '2026-05-31 09:00:00',
                'found_items_count' => null,
                'notes' => 'Revisión administrativa sin diferencias visibles.',
                'audited_by' => 'carolina.munoz@cnscgestion.local',
            ],
            [
                'dependency' => 'DEP-014',
                'audited_at' => '2026-06-12 12:20:00',
                'found_items_count' => null,
                'notes' => 'Inventario de enfermería actualizado con respaldo fotográfico pendiente.',
                'audited_by' => 'ivonne.reyes@cnscgestion.local',
            ],
            [
                'dependency' => 'DEP-015',
                'audited_at' => '2026-04-25 08:30:00',
                'found_items_count' => null,
                'notes' => 'Se recomienda revisar consumo semanal por stock bajo.',
                'audited_by' => 'ricardo.fuentes@cnscgestion.local',
            ],
            [
                'dependency' => 'DEP-001',
                'audited_at' => '2025-11-18 15:10:00',
                'found_items_count' => null,
                'notes' => 'Inventario histórico, requiere nueva revisión de sala.',
                'audited_by' => 'paula.vargas@cnscgestion.local',
            ],
        ];

        foreach ($audits as $audit) {
            $dependency = $this->dependency($audit['dependency']);
            $activeItems = InventoryItem::query()
                ->where('dependency_id', $dependency->id)
                ->where('active', true);
            $expected = (clone $activeItems)->count();
            $found = $audit['found_items_count'] ?? $expected;

            InventoryDependencyAudit::query()->updateOrCreate(
                [
                    'maintenance_dependency_id' => $dependency->id,
                    'audited_at' => $audit['audited_at'],
                ],
                [
                    'expected_items_count' => $expected,
                    'found_items_count' => $found,
                    'missing_items_count' => max($expected - $found, 0),
                    'critical_items_count' => (clone $activeItems)
                        ->whereIn('condition', ['Malo', 'Crítico', 'Inutilizable'])
                        ->count(),
                    'low_stock_items_count' => (clone $activeItems)
                        ->where('item_type', 'consumable')
                        ->whereNotNull('minimum_stock')
                        ->whereNotNull('stock_quantity')
                        ->whereColumn('stock_quantity', '<=', 'minimum_stock')
                        ->count(),
                    'notes' => $audit['notes'],
                    'audited_by' => $this->user($audit['audited_by'])->id,
                ],
            );
        }
    }
}
