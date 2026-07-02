<?php

namespace Database\Seeders;

use App\Models\DependencyType;
use App\Models\MaintenanceDependency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SchoolDependencySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Sala de conferencias', 'type' => 'Sala', 'location' => 'Edificio central', 'floor_sector' => 'Primer piso', 'capacity_max' => 40],
            ['name' => 'Biblioteca', 'type' => 'Espacio académico', 'location' => 'Sector CRA', 'floor_sector' => 'Primer piso', 'capacity_max' => 35],
            ['name' => 'Laboratorio de computación', 'type' => 'Laboratorio', 'location' => 'Pabellón académico', 'floor_sector' => 'Segundo piso', 'capacity_max' => 30],
            ['name' => 'Laboratorio de ciencias', 'type' => 'Laboratorio', 'location' => 'Pabellón académico', 'floor_sector' => 'Segundo piso', 'capacity_max' => 30],
            ['name' => 'Sala de música', 'type' => 'Sala', 'location' => 'Pabellón artístico', 'floor_sector' => 'Primer piso', 'capacity_max' => 25],
            ['name' => 'Sala de artes', 'type' => 'Sala', 'location' => 'Pabellón artístico', 'floor_sector' => 'Primer piso', 'capacity_max' => 25],
            ['name' => 'Gimnasio', 'type' => 'Espacio deportivo', 'location' => 'Patio principal', 'floor_sector' => 'Nivel patio', 'capacity_max' => 120],
            ['name' => 'Multicancha', 'type' => 'Espacio deportivo', 'location' => 'Patio principal', 'floor_sector' => 'Exterior', 'capacity_max' => 80],
            ['name' => 'Capilla', 'type' => 'Espacio pastoral', 'location' => 'Sector pastoral', 'floor_sector' => 'Primer piso', 'capacity_max' => 50],
            ['name' => 'Auditorio', 'type' => 'Espacio común', 'location' => 'Edificio central', 'floor_sector' => 'Primer piso', 'capacity_max' => 150],
            ['name' => 'Sala de profesores', 'type' => 'Espacio común', 'location' => 'Edificio central', 'floor_sector' => 'Segundo piso', 'capacity_max' => 25],
            ['name' => 'Sala PIE', 'type' => 'Espacio de apoyo escolar', 'location' => 'Pabellón apoyo', 'floor_sector' => 'Primer piso', 'capacity_max' => 20],
            ['name' => 'Sala de convivencia escolar', 'type' => 'Espacio de apoyo escolar', 'location' => 'Pabellón apoyo', 'floor_sector' => 'Primer piso', 'capacity_max' => 15],
            ['name' => 'Enfermería', 'type' => 'Espacio de apoyo escolar', 'location' => 'Edificio central', 'floor_sector' => 'Primer piso', 'capacity_max' => 10],
            ['name' => 'Comedor', 'type' => 'Espacio común', 'location' => 'Casino', 'floor_sector' => 'Primer piso', 'capacity_max' => 90],
            ['name' => 'Patio techado', 'type' => 'Espacio común', 'location' => 'Patio central', 'floor_sector' => 'Exterior', 'capacity_max' => 100],
            ['name' => 'Sala de reuniones', 'type' => 'Sala', 'location' => 'Edificio administrativo', 'floor_sector' => 'Segundo piso', 'capacity_max' => 14],
            ['name' => 'CRA', 'type' => 'Espacio académico', 'location' => 'Sector CRA', 'floor_sector' => 'Primer piso', 'capacity_max' => 30],
            ['name' => 'Oficina de atención de apoderados', 'type' => 'Oficina', 'location' => 'Edificio administrativo', 'floor_sector' => 'Primer piso', 'capacity_max' => 8],
        ];

        foreach ($defaults as $index => $dependency) {
            $type = DependencyType::query()->firstWhere('name', $dependency['type']);

            MaintenanceDependency::updateOrCreate(
                ['code' => 'DEP-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'dependency_type_id' => $type?->id,
                    'name' => $dependency['name'],
                    'description' => $dependency['name'],
                    'location' => $dependency['location'],
                    'floor_sector' => $dependency['floor_sector'],
                    'capacity_max' => $dependency['capacity_max'],
                    'available_equipment' => null,
                    'availability_status' => MaintenanceDependency::AVAILABILITY_AVAILABLE,
                    'calendar_color' => $type?->color ?: '#34c38f',
                    'requires_approval' => true,
                    'is_reservable' => true,
                    'distribution' => $dependency['location'],
                    'sector' => $dependency['floor_sector'],
                    'zone' => $dependency['location'],
                    'usage' => $dependency['type'],
                    'dependency_code' => Str::upper(Str::slug($dependency['name'], '')),
                    'active' => true,
                ],
            );
        }
    }
}
