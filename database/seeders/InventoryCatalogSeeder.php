<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use App\Models\InventorySubcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventoryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Tecnología',
                'slug' => 'tecnologia',
                'code_prefix' => 'TEC',
                'subcategories' => [
                    'Computadores',
                    'Notebooks',
                    'Tablets',
                    'Impresoras',
                    'Proyectores',
                    'Routers',
                    'Cámaras',
                    'Monitores',
                    'Periféricos',
                ],
            ],
            [
                'name' => 'Mobiliario',
                'slug' => 'mobiliario',
                'code_prefix' => 'MOB',
                'subcategories' => [
                    'Mesas',
                    'Sillas',
                    'Escritorios',
                    'Estantes',
                    'Casilleros',
                    'Pizarras',
                ],
            ],
            [
                'name' => 'Material pedagógico',
                'slug' => 'material_pedagogico',
                'code_prefix' => 'PED',
                'subcategories' => [
                    'Juegos didácticos',
                    'Instrumentos musicales',
                    'Material de aula',
                    'Recursos PIE',
                    'Material deportivo pedagógico',
                ],
            ],
            [
                'name' => 'Material de oficina',
                'slug' => 'material_oficina',
                'code_prefix' => 'OFI',
                'subcategories' => [
                    'Papelería',
                    'Tóner y tinta',
                    'Archivadores',
                    'Plumones y pizarras',
                ],
            ],
            [
                'name' => 'Herramientas',
                'slug' => 'herramientas',
                'code_prefix' => 'HER',
                'subcategories' => [
                    'Herramientas manuales',
                    'Herramientas eléctricas',
                ],
            ],
            [
                'name' => 'Deportes',
                'slug' => 'deportes',
                'code_prefix' => 'DEP',
                'subcategories' => [
                    'Balones',
                    'Implementos entrenamiento',
                    'Arcos y redes',
                ],
            ],
            [
                'name' => 'Seguridad',
                'slug' => 'seguridad',
                'code_prefix' => 'SEG',
                'subcategories' => [
                    'Extintores',
                    'Señaléticas',
                    'Cámaras de seguridad',
                    'Botiquines',
                    'Luces de emergencia',
                ],
            ],
            [
                'name' => 'Aseo y limpieza',
                'slug' => 'aseo_limpieza',
                'code_prefix' => 'ASE',
                'subcategories' => [
                    'Productos de limpieza',
                    'Implementos de aseo',
                ],
            ],
            [
                'name' => 'Equipamiento audiovisual',
                'slug' => 'audiovisual',
                'code_prefix' => 'AUD',
                'subcategories' => [
                    'Amplificación',
                    'Micrófonos',
                    'Parlantes',
                    'Pantallas',
                ],
            ],
            [
                'name' => 'Biblioteca',
                'slug' => 'biblioteca',
                'code_prefix' => 'BIB',
                'subcategories' => [
                    'Libros',
                    'Material audiovisual',
                ],
            ],
            [
                'name' => 'Enfermería',
                'slug' => 'enfermeria',
                'code_prefix' => 'ENF',
                'subcategories' => [
                    'Equipos médicos',
                    'Insumos',
                ],
            ],
            [
                'name' => 'Mantención',
                'slug' => 'mantencion',
                'code_prefix' => 'MAN',
                'subcategories' => [
                    'Repuestos',
                    'Insumos de reparación',
                ],
            ],
            [
                'name' => 'Laboratorio',
                'slug' => 'laboratorio',
                'code_prefix' => 'LAB',
                'subcategories' => [
                    'Instrumental',
                    'Reactivos',
                ],
            ],
            [
                'name' => 'Otros',
                'slug' => 'otros',
                'code_prefix' => 'OTR',
                'subcategories' => [],
            ],
        ];

        foreach ($categories as $categoryData) {
            $subcategories = $categoryData['subcategories'] ?? [];
            unset($categoryData['subcategories']);

            $category = InventoryCategory::updateOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['name'],
                    'code_prefix' => $categoryData['code_prefix'],
                    'description' => $categoryData['description'] ?? null,
                    'active' => true,
                ],
            );

            foreach ($subcategories as $subcategoryName) {
                $slug = Str::slug($subcategoryName, '_');

                InventorySubcategory::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'slug' => $slug,
                    ],
                    [
                        'name' => $subcategoryName,
                        'description' => null,
                        'active' => true,
                    ],
                );
            }
        }
    }
}

