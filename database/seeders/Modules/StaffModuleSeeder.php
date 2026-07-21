<?php

namespace Database\Seeders\Modules;

use App\Models\Department;
use Database\Seeders\Support\ModuleSeeder;
use Database\Seeders\Support\PreventsProductionSeeding;

class StaffModuleSeeder extends ModuleSeeder
{
    use PreventsProductionSeeding;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $region = $this->region('Los Ríos') ?: $this->region('Los Rios');
        $commune = $this->commune('Valdivia');

        $baseLocation = [
            'region' => $region?->name ?? 'Los Ríos',
            'region_id' => $region?->id,
            'commune' => $commune?->name ?? 'Valdivia',
            'commune_id' => $commune?->id,
            'address' => 'Av. Ramón Picarte 1450, Valdivia',
        ];

        $staffMembers = [
            [
                'email' => 'patricia.lopez@cnscgestion.local',
                'password' => 'Admin123!',
                'roles' => ['administrador'],
                'departments' => ['administracion', 'secretaria'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Patricia López Herrera',
                    'rut' => '11111111-1',
                    'birth_date' => '1985-02-11',
                    'institutional_email' => 'patricia.lopez@cnscgestion.local',
                    'personal_email' => 'patricia.lopez@example.com',
                    'phone' => '+56961000001',
                    'cargo_slug' => 'administrativo',
                    'contract_type' => 'indefinido',
                    'start_date' => '2021-03-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Administradora Pública',
                    'specialty' => 'Gestión administrativa',
                    'internal_notes' => 'Usuario administrador operativo para poblamiento demo.',
                ]),
            ],
            [
                'email' => 'carolina.munoz@cnscgestion.local',
                'password' => 'Direccion123!',
                'roles' => ['direccion'],
                'departments' => ['direccion'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Carolina Muñoz Arriagada',
                    'rut' => '12222222-2',
                    'birth_date' => '1978-10-20',
                    'institutional_email' => 'carolina.munoz@cnscgestion.local',
                    'personal_email' => 'carolina.munoz@example.com',
                    'phone' => '+56961000002',
                    'cargo_slug' => 'administrativo',
                    'contract_type' => 'indefinido',
                    'start_date' => '2019-03-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Profesora de Estado',
                    'specialty' => 'Gestión directiva',
                ]),
            ],
            [
                'email' => 'marcelo.rojas@cnscgestion.local',
                'password' => 'RRHH123!',
                'roles' => ['rrhh'],
                'departments' => ['recursos-humanos', 'administracion'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Marcelo Rojas Fuenzalida',
                    'rut' => '13333333-3',
                    'birth_date' => '1982-07-15',
                    'institutional_email' => 'marcelo.rojas@cnscgestion.local',
                    'personal_email' => 'marcelo.rojas@example.com',
                    'phone' => '+56961000003',
                    'cargo_slug' => 'administrativo',
                    'contract_type' => 'indefinido',
                    'start_date' => '2020-04-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Ingeniero en Administración',
                    'specialty' => 'Recursos humanos',
                ]),
            ],
            [
                'email' => 'paula.vargas@cnscgestion.local',
                'password' => 'Coord123!',
                'roles' => ['coordinador_academico'],
                'departments' => ['coordinacion-academica', 'docentes'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Paula Vargas Molina',
                    'rut' => '14444444-4',
                    'birth_date' => '1987-03-30',
                    'institutional_email' => 'paula.vargas@cnscgestion.local',
                    'personal_email' => 'paula.vargas@example.com',
                    'phone' => '+56961000004',
                    'cargo_slug' => 'coordinador_academico',
                    'contract_type' => 'indefinido',
                    'start_date' => '2022-03-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Profesora de Lenguaje',
                    'specialty' => 'Coordinación pedagógica',
                ]),
            ],
            [
                'email' => 'sergio.torres@cnscgestion.local',
                'password' => 'Inspect123!',
                'roles' => ['inspectoria'],
                'departments' => ['inspectoria-general'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Sergio Torres Álvarez',
                    'rut' => '15555555-5',
                    'birth_date' => '1980-11-08',
                    'institutional_email' => 'sergio.torres@cnscgestion.local',
                    'personal_email' => 'sergio.torres@example.com',
                    'phone' => '+56961000005',
                    'cargo_slug' => 'inspectoria',
                    'contract_type' => 'indefinido',
                    'start_date' => '2021-05-10',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Inspector general',
                    'specialty' => 'Convivencia y disciplina',
                ]),
            ],
            [
                'email' => 'laura.diaz@cnscgestion.local',
                'password' => 'Porteria123!',
                'roles' => ['porteria'],
                'departments' => ['inspectoria-general'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Laura Díaz Cárdenas',
                    'rut' => '16666666-6',
                    'birth_date' => '1990-04-12',
                    'institutional_email' => 'laura.diaz@cnscgestion.local',
                    'personal_email' => 'laura.diaz@example.com',
                    'phone' => '+56961000006',
                    'cargo_slug' => 'porteria',
                    'contract_type' => 'indefinido',
                    'start_date' => '2023-01-10',
                    'status' => 'activo',
                    'workday' => 'turnos',
                    'contract_hours' => 45,
                    'professional_title' => 'Técnico en administración',
                    'specialty' => 'Control de accesos',
                ]),
            ],
            [
                'email' => 'jose.campos@cnscgestion.local',
                'password' => 'Nochero123!',
                'roles' => ['nochero'],
                'departments' => ['inspectoria-general'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'José Campos Márquez',
                    'rut' => '17777777-7',
                    'birth_date' => '1988-09-25',
                    'institutional_email' => 'jose.campos@cnscgestion.local',
                    'personal_email' => 'jose.campos@example.com',
                    'phone' => '+56961000007',
                    'cargo_slug' => 'nochero',
                    'contract_type' => 'indefinido',
                    'start_date' => '2024-02-01',
                    'status' => 'activo',
                    'workday' => 'turnos',
                    'contract_hours' => 45,
                    'specialty' => 'Rondas nocturnas',
                ]),
            ],
            [
                'email' => 'ricardo.fuentes@cnscgestion.local',
                'password' => 'Manten123!',
                'roles' => ['encargado_mantencion'],
                'departments' => ['mantencion'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Ricardo Fuentes Leal',
                    'rut' => '18888888-8',
                    'birth_date' => '1979-08-18',
                    'institutional_email' => 'ricardo.fuentes@cnscgestion.local',
                    'personal_email' => 'ricardo.fuentes@example.com',
                    'phone' => '+56961000008',
                    'cargo_slug' => 'encargado_mantencion',
                    'contract_type' => 'indefinido',
                    'start_date' => '2020-08-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Técnico en electricidad',
                    'specialty' => 'Infraestructura y mantención',
                    'can_receive_maintenance_orders' => true,
                    'maintenance_role' => 'encargado_mantencion',
                ]),
            ],
            [
                'email' => 'nicolas.perez@cnscgestion.local',
                'password' => 'Riesgos123!',
                'roles' => ['prevencion_riesgos'],
                'departments' => ['prevencion-de-riesgos'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Nicolás Pérez Salazar',
                    'rut' => '19999999-9',
                    'birth_date' => '1984-01-17',
                    'institutional_email' => 'nicolas.perez@cnscgestion.local',
                    'personal_email' => 'nicolas.perez@example.com',
                    'phone' => '+56961000009',
                    'cargo_slug' => 'prevencion_riesgos',
                    'contract_type' => 'indefinido',
                    'start_date' => '2022-06-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Prevencionista de riesgos',
                    'specialty' => 'Seguridad escolar',
                ]),
            ],
            [
                'email' => 'camila.soto@cnscgestion.local',
                'password' => 'Psico123!',
                'roles' => ['psicologo'],
                'departments' => ['psicologia'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Camila Soto Benavides',
                    'rut' => '21111111-1',
                    'birth_date' => '1991-12-04',
                    'institutional_email' => 'camila.soto@cnscgestion.local',
                    'personal_email' => 'camila.soto@example.com',
                    'phone' => '+56961000010',
                    'cargo_slug' => 'psicologo',
                    'contract_type' => 'indefinido',
                    'start_date' => '2024-03-01',
                    'status' => 'activo',
                    'workday' => 'parcial',
                    'contract_hours' => 33,
                    'professional_title' => 'Psicóloga educacional',
                    'specialty' => 'Apoyo socioemocional',
                ]),
            ],
            [
                'email' => 'ivonne.reyes@cnscgestion.local',
                'password' => 'Enfer123!',
                'roles' => ['enfermeria'],
                'departments' => ['enfermeria'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Ivonne Reyes Gallardo',
                    'rut' => '22222222-2',
                    'birth_date' => '1986-05-22',
                    'institutional_email' => 'ivonne.reyes@cnscgestion.local',
                    'personal_email' => 'ivonne.reyes@example.com',
                    'phone' => '+56961000011',
                    'cargo_slug' => 'enfermeria',
                    'contract_type' => 'indefinido',
                    'start_date' => '2023-03-01',
                    'status' => 'activo',
                    'workday' => 'parcial',
                    'contract_hours' => 36,
                    'professional_title' => 'Enfermera universitaria',
                    'specialty' => 'Salud escolar',
                ]),
            ],
            [
                'email' => 'andrea.medina@cnscgestion.local',
                'password' => 'Docente123!',
                'roles' => ['docente'],
                'departments' => ['docentes'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Andrea Medina Contreras',
                    'rut' => '23333333-3',
                    'birth_date' => '1992-06-13',
                    'institutional_email' => 'andrea.medina@cnscgestion.local',
                    'personal_email' => 'andrea.medina@example.com',
                    'phone' => '+56961000012',
                    'cargo_slug' => 'docente',
                    'contract_type' => 'indefinido',
                    'start_date' => '2022-03-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 42,
                    'professional_title' => 'Profesora de Ciencias',
                    'specialty' => 'Biología',
                ]),
            ],
            [
                'email' => 'daniela.castillo@cnscgestion.local',
                'password' => 'Docente123!',
                'roles' => ['docente'],
                'departments' => ['docentes'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Daniela Castillo Sepúlveda',
                    'rut' => '24444444-4',
                    'birth_date' => '1994-01-09',
                    'institutional_email' => 'daniela.castillo@cnscgestion.local',
                    'personal_email' => 'daniela.castillo@example.com',
                    'phone' => '+56961000013',
                    'cargo_slug' => 'docente',
                    'contract_type' => 'plazo_fijo',
                    'start_date' => '2025-03-01',
                    'end_date' => '2026-12-31',
                    'status' => 'activo',
                    'workday' => 'parcial',
                    'contract_hours' => 34,
                    'professional_title' => 'Profesora de Matemática',
                    'specialty' => 'Matemática',
                ]),
            ],
        ];

        foreach ($staffMembers as $member) {
            $this->upsertStaffUser(
                $member['staff'],
                [
                    'email' => $member['email'],
                    'password' => $member['password'],
                    'name' => $member['staff']['full_name'],
                    'user_type' => 'staff',
                    'active' => true,
                ],
                $member['roles'],
                $member['departments'],
            );
        }

        $this->assignDepartmentResponsibles();
        $this->seedOrganigram();
    }

    private function assignDepartmentResponsibles(): void
    {
        $responsibles = [
            'direccion' => 'carolina.munoz@cnscgestion.local',
            'recursos-humanos' => 'marcelo.rojas@cnscgestion.local',
            'coordinacion-academica' => 'paula.vargas@cnscgestion.local',
            'inspectoria-general' => 'sergio.torres@cnscgestion.local',
            'mantencion' => 'ricardo.fuentes@cnscgestion.local',
            'prevencion-de-riesgos' => 'nicolas.perez@cnscgestion.local',
            'psicologia' => 'camila.soto@cnscgestion.local',
            'enfermeria' => 'ivonne.reyes@cnscgestion.local',
            'docentes' => 'paula.vargas@cnscgestion.local',
            'administracion' => 'patricia.lopez@cnscgestion.local',
            'secretaria' => 'patricia.lopez@cnscgestion.local',
        ];

        foreach ($responsibles as $slug => $email) {
            Department::query()
                ->where('slug', $slug)
                ->update(['responsible_staff_id' => $this->staffByEmail($email)->id]);
        }
    }

    private function seedOrganigram(): void
    {
        $carolina = $this->staffByEmail('carolina.munoz@cnscgestion.local');
        $marcelo = $this->staffByEmail('marcelo.rojas@cnscgestion.local');
        $paula = $this->staffByEmail('paula.vargas@cnscgestion.local');
        $sergio = $this->staffByEmail('sergio.torres@cnscgestion.local');

        $relations = [
            ['staff' => 'patricia.lopez@cnscgestion.local', 'related' => $marcelo, 'type' => 'direct_manager'],
            ['staff' => 'marcelo.rojas@cnscgestion.local', 'related' => $carolina, 'type' => 'direct_manager'],
            ['staff' => 'paula.vargas@cnscgestion.local', 'related' => $carolina, 'type' => 'direct_manager'],
            ['staff' => 'sergio.torres@cnscgestion.local', 'related' => $carolina, 'type' => 'direct_manager'],
            ['staff' => 'laura.diaz@cnscgestion.local', 'related' => $sergio, 'type' => 'direct_manager'],
            ['staff' => 'jose.campos@cnscgestion.local', 'related' => $sergio, 'type' => 'direct_manager'],
            ['staff' => 'ricardo.fuentes@cnscgestion.local', 'related' => $carolina, 'type' => 'direct_manager'],
            ['staff' => 'nicolas.perez@cnscgestion.local', 'related' => $carolina, 'type' => 'direct_manager'],
            ['staff' => 'camila.soto@cnscgestion.local', 'related' => $carolina, 'type' => 'direct_manager'],
            ['staff' => 'ivonne.reyes@cnscgestion.local', 'related' => $carolina, 'type' => 'direct_manager'],
            ['staff' => 'andrea.medina@cnscgestion.local', 'related' => $paula, 'type' => 'direct_manager'],
            ['staff' => 'andrea.medina@cnscgestion.local', 'related' => $marcelo, 'type' => 'hr', 'priority' => 2, 'primary' => false],
            ['staff' => 'andrea.medina@cnscgestion.local', 'related' => $carolina, 'type' => 'director', 'priority' => 3, 'primary' => false],
            ['staff' => 'daniela.castillo@cnscgestion.local', 'related' => $paula, 'type' => 'direct_manager'],
            ['staff' => 'daniela.castillo@cnscgestion.local', 'related' => $marcelo, 'type' => 'hr', 'priority' => 2, 'primary' => false],
            ['staff' => 'daniela.castillo@cnscgestion.local', 'related' => $carolina, 'type' => 'director', 'priority' => 3, 'primary' => false],
        ];

        foreach ($relations as $relation) {
            $this->upsertOrganigramRelation(
                $this->staffByEmail($relation['staff']),
                $relation['related'],
                $relation['type'],
                $relation['priority'] ?? 1,
                $relation['primary'] ?? true,
            );
        }
    }
}
