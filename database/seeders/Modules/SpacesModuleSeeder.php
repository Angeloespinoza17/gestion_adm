<?php

namespace Database\Seeders\Modules;

use App\Models\DependencyReservation;
use App\Models\DependencyReservationCollaborator;
use App\Models\MaintenanceDependency;
use Illuminate\Support\Str;
use Database\Seeders\SchoolDependencySeeder;
use Database\Seeders\Support\ModuleSeeder;

class SpacesModuleSeeder extends ModuleSeeder
{
    public function run(): void
    {
        $this->call(SchoolDependencySeeder::class);

        $this->seedDependencies();
        $this->seedReservations();
    }

    private function seedDependencies(): void
    {
        $defaults = [
            'DEP-001' => [
                'responsible' => 'paula.vargas@cnscgestion.local',
                'approvers' => ['carolina.munoz@cnscgestion.local', 'paula.vargas@cnscgestion.local'],
                'requires_approval' => true,
                'is_reservable' => true,
                'available_equipment' => 'Sistema de audio, proyector, pizarra digital',
                'notes' => 'Uso prioritario para reuniones docentes y jornadas internas.',
            ],
            'DEP-002' => [
                'responsible' => 'patricia.lopez@cnscgestion.local',
                'approvers' => ['paula.vargas@cnscgestion.local', 'patricia.lopez@cnscgestion.local'],
                'requires_approval' => false,
                'is_reservable' => true,
                'available_equipment' => 'Mesas de lectura, puntos de red y data show móvil',
            ],
            'DEP-003' => [
                'responsible' => 'ricardo.fuentes@cnscgestion.local',
                'approvers' => ['ricardo.fuentes@cnscgestion.local', 'patricia.lopez@cnscgestion.local'],
                'requires_approval' => true,
                'is_reservable' => true,
                'available_equipment' => '30 estaciones de trabajo, switch dedicado y proyector',
            ],
            'DEP-004' => [
                'responsible' => 'ricardo.fuentes@cnscgestion.local',
                'approvers' => ['ricardo.fuentes@cnscgestion.local', 'paula.vargas@cnscgestion.local'],
                'requires_approval' => true,
                'is_reservable' => true,
                'available_equipment' => 'Mesones, lavaojos, kit de reactivos y extractor',
            ],
            'DEP-007' => [
                'responsible' => 'sergio.torres@cnscgestion.local',
                'approvers' => ['sergio.torres@cnscgestion.local', 'paula.vargas@cnscgestion.local'],
                'requires_approval' => true,
                'is_reservable' => true,
            ],
            'DEP-008' => [
                'responsible' => 'sergio.torres@cnscgestion.local',
                'approvers' => ['sergio.torres@cnscgestion.local', 'paula.vargas@cnscgestion.local'],
                'requires_approval' => false,
                'is_reservable' => true,
            ],
            'DEP-009' => [
                'responsible' => 'carolina.munoz@cnscgestion.local',
                'approvers' => ['carolina.munoz@cnscgestion.local'],
                'requires_approval' => true,
                'is_reservable' => true,
            ],
            'DEP-010' => [
                'responsible' => 'carolina.munoz@cnscgestion.local',
                'approvers' => ['carolina.munoz@cnscgestion.local', 'patricia.lopez@cnscgestion.local'],
                'requires_approval' => true,
                'is_reservable' => true,
                'available_equipment' => 'Escenario, telón, sistema de amplificación y 120 butacas',
            ],
            'DEP-014' => [
                'responsible' => 'ivonne.reyes@cnscgestion.local',
                'approvers' => ['ivonne.reyes@cnscgestion.local'],
                'requires_approval' => false,
                'is_reservable' => false,
            ],
            'DEP-019' => [
                'responsible' => 'patricia.lopez@cnscgestion.local',
                'approvers' => ['patricia.lopez@cnscgestion.local'],
                'requires_approval' => false,
                'is_reservable' => false,
            ],
        ];

        foreach ($defaults as $code => $config) {
            $dependency = $this->dependency($code);
            $responsible = $this->staffByEmail($config['responsible']);
            $approverIds = collect($config['approvers'])
                ->map(fn (string $email) => $this->user($email)->id)
                ->all();

            $dependency->update([
                'responsible_staff_id' => $responsible->id,
                'requires_approval' => $config['requires_approval'],
                'is_reservable' => $config['is_reservable'],
                'dependency_kind' => MaintenanceDependency::KIND_SPACE,
                'is_inventory_auditable' => true,
                'is_maintenance_location' => true,
                'available_equipment' => $config['available_equipment'] ?? $dependency->available_equipment,
                'notes' => $config['notes'] ?? $dependency->notes,
            ]);

            $dependency->approvers()->sync($approverIds);
        }
    }

    private function seedReservations(): void
    {
        $reservations = [
            [
                'key' => ['title' => 'Consejo de profesores', 'starts_at' => '2026-07-02 15:30:00'],
                'dependency' => 'DEP-001',
                'staff' => 'paula.vargas@cnscgestion.local',
                'department' => 'docentes',
                'created_by' => 'paula.vargas@cnscgestion.local',
                'approved_by' => 'carolina.munoz@cnscgestion.local',
                'status' => DependencyReservation::STATUS_APPROVED,
                'activity' => 'Revisión de resultados del primer semestre y planificación de julio.',
                'ends_at' => '2026-07-02 17:00:00',
                'estimated_attendees' => 28,
                'special_requirements' => 'Proyector activo, café y disposición tipo directorio.',
                'approved_at' => '2026-06-29 09:10:00',
                'collaborators' => [
                    ['staff' => 'patricia.lopez@cnscgestion.local'],
                ],
            ],
            [
                'key' => ['title' => 'Capacitación PIE', 'starts_at' => '2026-07-08 09:00:00'],
                'dependency' => 'DEP-010',
                'staff' => 'camila.soto@cnscgestion.local',
                'department' => 'psicologia',
                'created_by' => 'camila.soto@cnscgestion.local',
                'status' => DependencyReservation::STATUS_PENDING,
                'activity' => 'Jornada interna con docentes sobre adecuaciones curriculares.',
                'ends_at' => '2026-07-08 12:30:00',
                'estimated_attendees' => 42,
                'special_requirements' => 'Micrófono inalámbrico y acceso a escenario.',
            ],
            [
                'key' => ['title' => 'Feria científica 7° básico', 'starts_at' => '2026-06-12 09:00:00'],
                'dependency' => 'DEP-004',
                'staff' => 'andrea.medina@cnscgestion.local',
                'department' => 'docentes',
                'created_by' => 'andrea.medina@cnscgestion.local',
                'approved_by' => 'paula.vargas@cnscgestion.local',
                'status' => DependencyReservation::STATUS_FINISHED,
                'activity' => 'Montaje y evaluación interna de experimentos del nivel.',
                'ends_at' => '2026-06-12 13:00:00',
                'estimated_attendees' => 24,
                'approved_at' => '2026-06-02 08:30:00',
                'collaborators' => [
                    ['staff' => 'daniela.castillo@cnscgestion.local'],
                    ['external_email' => 'apoyo.laboratorio@cnscgestion.local'],
                ],
            ],
            [
                'key' => ['title' => 'Ensayo pastoral', 'starts_at' => '2026-06-20 14:00:00'],
                'dependency' => 'DEP-009',
                'staff' => 'daniela.castillo@cnscgestion.local',
                'department' => 'docentes',
                'created_by' => 'daniela.castillo@cnscgestion.local',
                'approved_by' => 'carolina.munoz@cnscgestion.local',
                'status' => DependencyReservation::STATUS_REJECTED,
                'activity' => 'Ensayo general de actividad de cierre con estudiantes.',
                'ends_at' => '2026-06-20 15:30:00',
                'rejected_at' => '2026-06-18 18:00:00',
                'observations' => 'Fecha rechazada por superposición con acto institucional.',
            ],
            [
                'key' => ['title' => 'Reunión de apoderados 2° medio', 'starts_at' => '2026-07-10 18:00:00'],
                'dependency' => 'DEP-017',
                'staff' => 'sergio.torres@cnscgestion.local',
                'department' => 'inspectoria-general',
                'created_by' => 'sergio.torres@cnscgestion.local',
                'approved_by' => 'carolina.munoz@cnscgestion.local',
                'cancelled_by' => 'sergio.torres@cnscgestion.local',
                'status' => DependencyReservation::STATUS_CANCELLED,
                'activity' => 'Citación extraordinaria por protocolo de convivencia.',
                'ends_at' => '2026-07-10 19:30:00',
                'approved_at' => '2026-07-01 11:00:00',
                'cancelled_at' => '2026-07-09 08:15:00',
                'observations' => 'Suspendida por cambio de calendario institucional.',
            ],
            [
                'key' => ['title' => 'Taller de reforzamiento lector', 'starts_at' => '2026-06-03 16:00:00'],
                'dependency' => 'DEP-002',
                'staff' => 'paula.vargas@cnscgestion.local',
                'department' => 'coordinacion-academica',
                'created_by' => 'paula.vargas@cnscgestion.local',
                'approved_by' => 'paula.vargas@cnscgestion.local',
                'status' => DependencyReservation::STATUS_APPROVED,
                'activity' => 'Bloque semanal de apoyo pedagógico para estudiantes de 5° básico.',
                'ends_at' => '2026-06-03 17:30:00',
                'estimated_attendees' => 15,
                'repetition_type' => 'weekly',
                'repetition_until' => '2026-07-29',
                'series_uuid' => 'b5d97a57-0b16-4b1a-91c8-4cbf3240f001',
                'approved_at' => '2026-05-29 17:40:00',
                'collaborators' => [
                    ['staff' => 'andrea.medina@cnscgestion.local'],
                ],
            ],
        ];

        foreach ($reservations as $definition) {
            $staff = $this->staffByEmail($definition['staff']);
            $creator = $this->user($definition['created_by']);
            $dependency = $this->dependency($definition['dependency']);

            $reservation = DependencyReservation::query()->updateOrCreate(
                $definition['key'],
                [
                    'maintenance_dependency_id' => $dependency->id,
                    'staff_id' => $staff->id,
                    'department_id' => isset($definition['department']) ? $this->department($definition['department'])->id : null,
                    'activity' => $definition['activity'] ?? null,
                    'ends_at' => $definition['ends_at'],
                    'repetition_type' => $definition['repetition_type'] ?? 'none',
                    'repetition_until' => $definition['repetition_until'] ?? null,
                    'series_uuid' => $definition['series_uuid'] ?? null,
                    'status' => $definition['status'],
                    'observations' => $definition['observations'] ?? null,
                    'estimated_attendees' => $definition['estimated_attendees'] ?? null,
                    'special_requirements' => $definition['special_requirements'] ?? null,
                    'created_by' => $creator->id,
                    'approved_by' => isset($definition['approved_by']) ? $this->user($definition['approved_by'])->id : null,
                    'cancelled_by' => isset($definition['cancelled_by']) ? $this->user($definition['cancelled_by'])->id : null,
                    'approved_at' => $definition['approved_at'] ?? null,
                    'rejected_at' => $definition['rejected_at'] ?? null,
                    'cancelled_at' => $definition['cancelled_at'] ?? null,
                ],
            );

            foreach ($definition['collaborators'] ?? [] as $collaborator) {
                DependencyReservationCollaborator::query()->updateOrCreate(
                    [
                        'dependency_reservation_id' => $reservation->id,
                        'staff_id' => isset($collaborator['staff']) ? $this->staffByEmail($collaborator['staff'])->id : null,
                        'external_email' => $collaborator['external_email'] ?? null,
                    ],
                    [],
                );
            }
        }
    }
}
