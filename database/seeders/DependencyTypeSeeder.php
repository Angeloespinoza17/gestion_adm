<?php

namespace Database\Seeders;

use App\Models\DependencyType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DependencyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Sala', 'description' => 'Sala de uso académico o reuniones.', 'color' => '#34c38f', 'sort_order' => 10],
            ['name' => 'Laboratorio', 'description' => 'Espacio equipado para trabajo práctico.', 'color' => '#556ee6', 'sort_order' => 20],
            ['name' => 'Oficina', 'description' => 'Espacio administrativo o de atención.', 'color' => '#50a5f1', 'sort_order' => 30],
            ['name' => 'Espacio deportivo', 'description' => 'Recintos para actividad física y deporte.', 'color' => '#f1b44c', 'sort_order' => 40],
            ['name' => 'Espacio pastoral', 'description' => 'Espacios vinculados a pastoral o vida espiritual.', 'color' => '#f46a6a', 'sort_order' => 50],
            ['name' => 'Espacio administrativo', 'description' => 'Dependencias de apoyo administrativo.', 'color' => '#74788d', 'sort_order' => 60],
            ['name' => 'Espacio académico', 'description' => 'Recintos de apoyo académico.', 'color' => '#2ab57d', 'sort_order' => 70],
            ['name' => 'Espacio de apoyo escolar', 'description' => 'Recintos de apoyo PIE, convivencia u orientación.', 'color' => '#f46a6a', 'sort_order' => 80],
            ['name' => 'Espacio común', 'description' => 'Áreas compartidas por la comunidad educativa.', 'color' => '#50a5f1', 'sort_order' => 90],
        ];

        foreach ($types as $type) {
            DependencyType::updateOrCreate(
                ['slug' => Str::slug($type['name'])],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'color' => $type['color'],
                    'sort_order' => $type['sort_order'],
                    'active' => true,
                ],
            );
        }
    }
}
