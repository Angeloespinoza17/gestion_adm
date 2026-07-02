<?php

namespace Database\Seeders\Modules;

use App\Models\InventoryItem;
use App\Models\Supplier;
use Database\Seeders\Support\ModuleSeeder;

class InventoryModuleSeeder extends ModuleSeeder
{
    public function run(): void
    {
        $this->seedSuppliers();
        $this->seedItems();
    }

    private function seedSuppliers(): void
    {
        $suppliers = [
            [
                'rut' => '76000001-1',
                'name' => 'TecnoAula',
                'business_name' => 'TecnoAula SpA',
                'email' => 'ventas@tecnoaula.cl',
                'phone' => '+56224567890',
                'address' => 'Av. Pedro de Valdivia 1200, Santiago',
            ],
            [
                'rut' => '76000002-2',
                'name' => 'Muebles del Sur',
                'business_name' => 'Muebles del Sur Ltda.',
                'email' => 'contacto@mueblesdelsur.cl',
                'phone' => '+56632233445',
                'address' => 'Ruta T-350 Km 4, Valdivia',
            ],
            [
                'rut' => '76000003-3',
                'name' => 'SegurEduca',
                'business_name' => 'SegurEduca Chile',
                'email' => 'comercial@segureduca.cl',
                'phone' => '+56226781234',
                'address' => 'Av. Apoquindo 4567, Las Condes',
            ],
            [
                'rut' => '76000004-4',
                'name' => 'Insumos Médicos Austral',
                'business_name' => 'Insumos Médicos Austral SpA',
                'email' => 'pedidos@im-austral.cl',
                'phone' => '+56632234567',
                'address' => 'Picarte 980, Valdivia',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(
                ['rut' => $supplier['rut']],
                array_merge($supplier, ['active' => true]),
            );
        }
    }

    private function seedItems(): void
    {
        $items = [
            [
                'code' => 'TEC-0001',
                'category' => 'tecnologia',
                'subcategory' => 'notebooks',
                'name' => 'Notebook Lenovo ThinkBook 14',
                'brand' => 'Lenovo',
                'model' => 'ThinkBook 14 G4',
                'serial_number' => 'LEN-THB14-0001',
                'purchase_date' => '2025-03-15',
                'purchase_value' => 780000,
                'useful_life_years' => 5,
                'status' => 'En uso',
                'condition' => 'Bueno',
                'dependency' => 'DEP-003',
                'responsible' => 'patricia.lopez@cnscgestion.local',
                'supplier_rut' => '76000001-1',
                'description' => 'Equipo destinado al laboratorio de computación para apoyo de clases.',
                'movements' => [
                    [
                        'movement_type' => 'Asignación inicial',
                        'movement_date' => '2025-03-20',
                        'to_dependency' => 'DEP-003',
                        'to_user' => 'patricia.lopez@cnscgestion.local',
                        'reason' => 'Alta en inventario para laboratorio',
                        'created_by' => 'patricia.lopez@cnscgestion.local',
                    ],
                ],
            ],
            [
                'code' => 'TEC-0002',
                'category' => 'tecnologia',
                'subcategory' => 'proyectores',
                'name' => 'Proyector Epson PowerLite',
                'brand' => 'Epson',
                'model' => 'PowerLite E20',
                'serial_number' => 'EPS-PJ-0015',
                'purchase_date' => '2024-08-10',
                'purchase_value' => 460000,
                'useful_life_years' => 6,
                'status' => 'Activo',
                'condition' => 'Bueno',
                'dependency' => 'DEP-001',
                'responsible' => 'paula.vargas@cnscgestion.local',
                'supplier_rut' => '76000001-1',
                'description' => 'Proyector móvil de reuniones académicas.',
                'movements' => [
                    [
                        'movement_type' => 'Asignación inicial',
                        'movement_date' => '2024-08-15',
                        'to_dependency' => 'DEP-001',
                        'to_user' => 'paula.vargas@cnscgestion.local',
                        'reason' => 'Disponibilizado para sala de conferencias',
                        'created_by' => 'patricia.lopez@cnscgestion.local',
                    ],
                ],
            ],
            [
                'code' => 'TEC-0003',
                'category' => 'seguridad',
                'subcategory' => 'camaras_de_seguridad',
                'name' => 'Cámara IP domo acceso norte',
                'brand' => 'Hikvision',
                'model' => 'DS-2CD2143',
                'serial_number' => 'CAM-NORTE-004',
                'purchase_date' => '2023-11-02',
                'purchase_value' => 185000,
                'useful_life_years' => 5,
                'status' => 'Pendiente de revisión',
                'condition' => 'Crítico',
                'dependency' => 'DEP-010',
                'responsible' => 'nicolas.perez@cnscgestion.local',
                'supplier_rut' => '76000003-3',
                'description' => 'Cámara con intermitencias en enlace de red.',
                'movements' => [
                    [
                        'movement_type' => 'Asignación inicial',
                        'movement_date' => '2023-11-05',
                        'to_dependency' => 'DEP-010',
                        'to_user' => 'nicolas.perez@cnscgestion.local',
                        'reason' => 'Instalación en acceso norte',
                        'created_by' => 'patricia.lopez@cnscgestion.local',
                    ],
                ],
            ],
            [
                'code' => 'MOB-0001',
                'category' => 'mobiliario',
                'subcategory' => 'escritorios',
                'name' => 'Escritorio ejecutivo dirección',
                'brand' => 'Muebles del Sur',
                'model' => 'Lenga 180',
                'serial_number' => 'MDS-ESC-0021',
                'purchase_date' => '2022-04-18',
                'purchase_value' => 320000,
                'useful_life_years' => 8,
                'status' => 'En uso',
                'condition' => 'Bueno',
                'dependency' => 'DEP-019',
                'responsible' => 'carolina.munoz@cnscgestion.local',
                'supplier_rut' => '76000002-2',
                'description' => 'Mobiliario principal de oficina de atención a apoderados.',
                'movements' => [
                    [
                        'movement_type' => 'Asignación inicial',
                        'movement_date' => '2022-04-25',
                        'to_dependency' => 'DEP-019',
                        'to_user' => 'carolina.munoz@cnscgestion.local',
                        'reason' => 'Implementación oficina administrativa',
                        'created_by' => 'patricia.lopez@cnscgestion.local',
                    ],
                ],
            ],
            [
                'code' => 'SEG-0001',
                'category' => 'seguridad',
                'subcategory' => 'extintores',
                'name' => 'Extintor CO2 auditorio',
                'brand' => 'Ansul',
                'model' => 'CO2 5kg',
                'serial_number' => 'EXT-AUD-0007',
                'purchase_date' => '2024-01-15',
                'purchase_value' => 129000,
                'useful_life_years' => 10,
                'status' => 'Activo',
                'condition' => 'Regular',
                'dependency' => 'DEP-010',
                'responsible' => 'ricardo.fuentes@cnscgestion.local',
                'supplier_rut' => '76000003-3',
                'description' => 'Extintor del auditorio con mantención semestral.',
                'movements' => [
                    [
                        'movement_type' => 'Instalación',
                        'movement_date' => '2024-01-20',
                        'to_dependency' => 'DEP-010',
                        'to_user' => 'ricardo.fuentes@cnscgestion.local',
                        'reason' => 'Reemplazo extintor vencido',
                        'created_by' => 'ricardo.fuentes@cnscgestion.local',
                    ],
                ],
            ],
            [
                'code' => 'ENF-0001',
                'category' => 'enfermeria',
                'subcategory' => 'equipos_medicos',
                'name' => 'Tensiómetro digital',
                'brand' => 'Omron',
                'model' => 'HEM-7120',
                'serial_number' => 'OMR-HEM-2026',
                'purchase_date' => '2026-03-12',
                'purchase_value' => 59000,
                'useful_life_years' => 4,
                'status' => 'En uso',
                'condition' => 'Nuevo',
                'dependency' => 'DEP-014',
                'responsible' => 'ivonne.reyes@cnscgestion.local',
                'supplier_rut' => '76000004-4',
                'description' => 'Equipo de control preventivo de signos vitales en enfermería.',
                'movements' => [
                    [
                        'movement_type' => 'Ingreso clínico',
                        'movement_date' => '2026-03-14',
                        'to_dependency' => 'DEP-014',
                        'to_user' => 'ivonne.reyes@cnscgestion.local',
                        'reason' => 'Habilitación de módulo de salud escolar',
                        'created_by' => 'patricia.lopez@cnscgestion.local',
                    ],
                ],
            ],
            [
                'code' => 'ASE-0001',
                'category' => 'aseo_limpieza',
                'subcategory' => 'productos_de_limpieza',
                'name' => 'Cloro gel institucional 5L',
                'brand' => 'CleanPro',
                'model' => 'Bidón 5L',
                'purchase_date' => '2026-06-01',
                'purchase_value' => 8500,
                'status' => 'En bodega',
                'condition' => 'Bueno',
                'dependency' => 'DEP-015',
                'responsible' => 'ricardo.fuentes@cnscgestion.local',
                'supplier_rut' => '76000003-3',
                'description' => 'Insumo de limpieza para casino y espacios comunes.',
                'item_type' => 'consumable',
                'stock_quantity' => 8,
                'minimum_stock' => 10,
                'unit_of_measure' => 'bidones',
                'stock_movements' => [
                    [
                        'movement_type' => 'in',
                        'quantity' => 20,
                        'previous_stock' => 0,
                        'new_stock' => 20,
                        'reason' => 'Compra inicial junio',
                        'created_by' => 'patricia.lopez@cnscgestion.local',
                    ],
                    [
                        'movement_type' => 'out',
                        'quantity' => 8,
                        'previous_stock' => 20,
                        'new_stock' => 12,
                        'reason' => 'Distribución semanal casino y patios',
                        'created_by' => 'ricardo.fuentes@cnscgestion.local',
                    ],
                    [
                        'movement_type' => 'adjust',
                        'quantity' => 4,
                        'previous_stock' => 12,
                        'new_stock' => 8,
                        'reason' => 'Ajuste por merma inventariada',
                        'created_by' => 'ricardo.fuentes@cnscgestion.local',
                    ],
                ],
            ],
            [
                'code' => 'OFI-0001',
                'category' => 'material_oficina',
                'subcategory' => 'toner_y_tinta',
                'name' => 'Tóner HP 83A',
                'brand' => 'HP',
                'model' => 'CF283A',
                'purchase_date' => '2026-05-25',
                'purchase_value' => 52000,
                'status' => 'En bodega',
                'condition' => 'Bueno',
                'dependency' => 'DEP-019',
                'responsible' => 'patricia.lopez@cnscgestion.local',
                'supplier_rut' => '76000001-1',
                'description' => 'Stock de impresión para administración y dirección.',
                'item_type' => 'consumable',
                'stock_quantity' => 2,
                'minimum_stock' => 3,
                'unit_of_measure' => 'cajas',
                'stock_movements' => [
                    [
                        'movement_type' => 'in',
                        'quantity' => 6,
                        'previous_stock' => 0,
                        'new_stock' => 6,
                        'reason' => 'Compra segundo trimestre',
                        'created_by' => 'patricia.lopez@cnscgestion.local',
                    ],
                    [
                        'movement_type' => 'out',
                        'quantity' => 4,
                        'previous_stock' => 6,
                        'new_stock' => 2,
                        'reason' => 'Reposición impresoras dirección y RRHH',
                        'created_by' => 'patricia.lopez@cnscgestion.local',
                    ],
                ],
            ],
            [
                'code' => 'LAB-0001',
                'category' => 'laboratorio',
                'subcategory' => 'reactivos',
                'name' => 'Reactivo indicador de pH',
                'brand' => 'Merck',
                'model' => 'Tiras reactivas',
                'purchase_date' => '2026-04-08',
                'purchase_value' => 18000,
                'status' => 'Activo',
                'condition' => 'Bueno',
                'dependency' => 'DEP-004',
                'responsible' => 'andrea.medina@cnscgestion.local',
                'supplier_rut' => '76000004-4',
                'description' => 'Material de apoyo para prácticas de ciencias.',
                'item_type' => 'consumable',
                'stock_quantity' => 20,
                'minimum_stock' => 5,
                'unit_of_measure' => 'frascos',
                'stock_movements' => [
                    [
                        'movement_type' => 'in',
                        'quantity' => 24,
                        'previous_stock' => 0,
                        'new_stock' => 24,
                        'reason' => 'Compra laboratorio semestre 1',
                        'created_by' => 'patricia.lopez@cnscgestion.local',
                    ],
                    [
                        'movement_type' => 'out',
                        'quantity' => 4,
                        'previous_stock' => 24,
                        'new_stock' => 20,
                        'reason' => 'Uso en prácticas de 7° y 8° básico',
                        'created_by' => 'andrea.medina@cnscgestion.local',
                    ],
                ],
            ],
        ];

        foreach ($items as $definition) {
            $item = InventoryItem::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'qr_code' => 'qr://' . $definition['code'],
                    'barcode' => $definition['code'],
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'category_id' => $this->category($definition['category'])->id,
                    'subcategory_id' => $this->subcategory($definition['category'], $definition['subcategory'])->id,
                    'brand' => $definition['brand'] ?? null,
                    'model' => $definition['model'] ?? null,
                    'serial_number' => $definition['serial_number'] ?? null,
                    'purchase_date' => $definition['purchase_date'] ?? null,
                    'purchase_value' => $definition['purchase_value'] ?? null,
                    'useful_life_years' => $definition['useful_life_years'] ?? null,
                    'status' => $definition['status'] ?? 'Activo',
                    'condition' => $definition['condition'] ?? 'Bueno',
                    'dependency_id' => isset($definition['dependency']) ? $this->dependency($definition['dependency'])->id : null,
                    'responsible_user_id' => isset($definition['responsible']) ? $this->user($definition['responsible'])->id : null,
                    'supplier_id' => isset($definition['supplier_rut']) ? Supplier::query()->where('rut', $definition['supplier_rut'])->value('id') : null,
                    'active' => true,
                    'item_type' => $definition['item_type'] ?? 'asset',
                    'stock_quantity' => $definition['stock_quantity'] ?? null,
                    'minimum_stock' => $definition['minimum_stock'] ?? null,
                    'unit_of_measure' => $definition['unit_of_measure'] ?? null,
                    'created_by' => $this->creator()->id,
                    'updated_by' => $this->creator()->id,
                ],
            );

            foreach ($definition['movements'] ?? [] as $movement) {
                $item->movements()->updateOrCreate(
                    [
                        'movement_type' => $movement['movement_type'],
                        'movement_date' => $movement['movement_date'],
                        'reason' => $movement['reason'],
                    ],
                    [
                        'from_dependency_id' => isset($movement['from_dependency']) ? $this->dependency($movement['from_dependency'])->id : null,
                        'to_dependency_id' => isset($movement['to_dependency']) ? $this->dependency($movement['to_dependency'])->id : null,
                        'from_user_id' => isset($movement['from_user']) ? $this->user($movement['from_user'])->id : null,
                        'to_user_id' => isset($movement['to_user']) ? $this->user($movement['to_user'])->id : null,
                        'observations' => $movement['observations'] ?? null,
                        'created_by' => isset($movement['created_by']) ? $this->user($movement['created_by'])->id : $this->creator()->id,
                    ],
                );
            }

            foreach ($definition['stock_movements'] ?? [] as $movement) {
                $item->stockMovements()->updateOrCreate(
                    [
                        'movement_type' => $movement['movement_type'],
                        'quantity' => $movement['quantity'],
                        'reason' => $movement['reason'],
                    ],
                    [
                        'previous_stock' => $movement['previous_stock'],
                        'new_stock' => $movement['new_stock'],
                        'created_by' => isset($movement['created_by']) ? $this->user($movement['created_by'])->id : $this->creator()->id,
                    ],
                );
            }
        }
    }
}
