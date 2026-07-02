<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SchoolDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Dirección', 'color' => '#0d6efd'],
            ['name' => 'Inspectoría General', 'color' => '#6f42c1'],
            ['name' => 'Unidad Técnico Pedagógica', 'color' => '#198754'],
            ['name' => 'Convivencia Escolar', 'color' => '#dc3545'],
            ['name' => 'Orientación', 'color' => '#fd7e14'],
            ['name' => 'Psicología', 'color' => '#6610f2'],
            ['name' => 'Enfermería', 'color' => '#20c997'],
            ['name' => 'Administración', 'color' => '#495057'],
            ['name' => 'Secretaría', 'color' => '#6c757d'],
            ['name' => 'Biblioteca', 'color' => '#0dcaf0'],
            ['name' => 'CRA', 'color' => '#17a2b8'],
            ['name' => 'Informática', 'color' => '#0b5ed7'],
            ['name' => 'Mantención', 'color' => '#ffc107'],
            ['name' => 'Auxiliares', 'color' => '#adb5bd'],
            ['name' => 'Docentes', 'color' => '#198754'],
            ['name' => 'Educadoras de Párvulos', 'color' => '#d63384'],
            ['name' => 'Asistentes de la Educación', 'color' => '#6f42c1'],
            ['name' => 'Coordinación Académica', 'color' => '#198754'],
            ['name' => 'Pastoral', 'color' => '#7952b3'],
            ['name' => 'Religión', 'color' => '#8c68cd'],
            ['name' => 'PIE', 'color' => '#e83e8c', 'description' => 'Programa de Integración Escolar'],
            ['name' => 'SEP', 'color' => '#fd7e14'],
            ['name' => 'Extraescolar', 'color' => '#198754'],
            ['name' => 'Comunicaciones', 'color' => '#0dcaf0'],
            ['name' => 'Prevención de Riesgos', 'color' => '#dc3545'],
            ['name' => 'Finanzas', 'color' => '#20c997'],
            ['name' => 'Recursos Humanos', 'color' => '#0d6efd'],
        ];

        foreach ($departments as $index => $department) {
            $name = $department['name'];

            Department::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $department['description'] ?? null,
                    'active' => true,
                    'color' => $department['color'] ?? null,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
