<?php

namespace Database\Seeders\Modules;

use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityIncidentAssignment;
use App\Models\Security\SecurityIncidentComment;
use App\Models\Security\SecurityIncidentStatus;
use App\Models\Security\SecurityNotification;
use App\Models\Security\SecurityRound;
use App\Models\Security\SecurityRoundSector;
use App\Models\Security\SecurityShift;
use Database\Seeders\Support\ModuleSeeder;

class SecurityModuleSeeder extends ModuleSeeder
{
    public function run(): void
    {
        $shifts = $this->seedShifts();
        $this->seedRoundsAndIncidents($shifts);
    }

    /**
     * @return array<string, \App\Models\Security\SecurityShift>
     */
    private function seedShifts(): array
    {
        $creator = $this->user('patricia.lopez@cnscgestion.local');
        $nochero = $this->staffByEmail('jose.campos@cnscgestion.local');
        $startedBy = $this->user('jose.campos@cnscgestion.local');
        $templateDependency = $this->dependency('DEP-010');

        $template = SecurityShift::query()->updateOrCreate(
            [
                'staff_id' => $nochero->id,
                'schedule_type' => SecurityShift::SCHEDULE_WEEKLY,
                'recurrence_starts_on' => '2026-03-02',
                'coverage_label' => 'Todo el colegio',
            ],
            [
                'maintenance_dependency_id' => $templateDependency->id,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
                'scheduled_start_at' => '2026-03-02 22:00:00',
                'scheduled_end_at' => '2026-03-03 06:00:00',
                'weekdays' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'template_start_time' => '22:00:00',
                'template_end_time' => '06:00:00',
                'recurrence_ends_on' => '2026-12-31',
                'status' => SecurityShift::STATUS_PROGRAMADO,
                'general_observations' => 'Plantilla semanal de rondas nocturnas.',
            ],
        );

        $generated = SecurityShift::query()->updateOrCreate(
            [
                'parent_shift_id' => $template->id,
                'generated_for_date' => '2026-06-25',
            ],
            [
                'staff_id' => $nochero->id,
                'schedule_type' => SecurityShift::SCHEDULE_SINGLE,
                'maintenance_dependency_id' => $templateDependency->id,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
                'started_by_user_id' => $startedBy->id,
                'closed_by_user_id' => $startedBy->id,
                'scheduled_start_at' => '2026-06-25 22:00:00',
                'scheduled_end_at' => '2026-06-26 06:00:00',
                'started_at' => '2026-06-25 22:05:00',
                'ended_at' => '2026-06-26 05:58:00',
                'status' => SecurityShift::STATUS_FINALIZADO,
                'coverage_label' => 'Todo el colegio',
                'general_observations' => 'Turno generado desde plantilla semanal.',
                'closing_observations' => 'Se cierra con dos novedades registradas y una crítica derivada.',
            ],
        );

        $active = SecurityShift::query()->updateOrCreate(
            [
                'staff_id' => $nochero->id,
                'schedule_type' => SecurityShift::SCHEDULE_SINGLE,
                'scheduled_start_at' => '2026-06-27 22:00:00',
            ],
            [
                'maintenance_dependency_id' => $templateDependency->id,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
                'started_by_user_id' => $startedBy->id,
                'scheduled_end_at' => '2026-06-28 06:00:00',
                'started_at' => '2026-06-27 22:03:00',
                'status' => SecurityShift::STATUS_EN_CURSO,
                'coverage_label' => 'Todo el colegio',
                'general_observations' => 'Turno activo de fin de semana.',
            ],
        );

        $cancelled = SecurityShift::query()->updateOrCreate(
            [
                'staff_id' => $nochero->id,
                'schedule_type' => SecurityShift::SCHEDULE_SINGLE,
                'scheduled_start_at' => '2026-06-29 22:00:00',
            ],
            [
                'maintenance_dependency_id' => $templateDependency->id,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
                'scheduled_end_at' => '2026-06-30 06:00:00',
                'status' => SecurityShift::STATUS_CANCELADO,
                'coverage_label' => 'Todo el colegio',
                'general_observations' => 'Turno cancelado por ajuste de pauta.',
                'closing_observations' => 'Cobertura absorbida por plantilla semanal.',
            ],
        );

        return compact('template', 'generated', 'active', 'cancelled');
    }

    /**
     * @param  array<string, \App\Models\Security\SecurityShift>  $shifts
     */
    private function seedRoundsAndIncidents(array $shifts): void
    {
        $nocheroUser = $this->user('jose.campos@cnscgestion.local');
        $sergioUser = $this->user('sergio.torres@cnscgestion.local');
        $ricardoUser = $this->user('ricardo.fuentes@cnscgestion.local');
        $carolinaUser = $this->user('carolina.munoz@cnscgestion.local');

        $statusPending = $this->incidentStatus('pendiente');
        $statusReview = $this->incidentStatus('en_revision');
        $statusDerived = $this->incidentStatus('derivada');
        $statusResolved = $this->incidentStatus('resuelta');

        $roundOne = SecurityRound::query()->updateOrCreate(
            [
                'security_shift_id' => $shifts['generated']->id,
                'round_number' => 1,
            ],
            [
                'recorded_by_user_id' => $nocheroUser->id,
                'recorded_at' => '2026-06-25 23:15:00',
                'overall_status' => SecurityRound::STATUS_OBSERVADO,
                'observations' => 'Se detecta puerta lateral mal cerrada en laboratorio.',
                'nochero_confirmation_name' => $shifts['generated']->staff?->full_name ?: $nocheroUser->name,
                'signature_data' => '{"seeded":true,"signed":true}',
                'latitude' => -39.8142000,
                'longitude' => -73.2451000,
                'location_accuracy' => 6.5,
                'act_number' => sprintf('ACTA-RS-20260625-%04d-01', $shifts['generated']->id),
                'act_generated_at' => '2026-06-25 23:20:00',
            ],
        );

        $roundTwo = SecurityRound::query()->updateOrCreate(
            [
                'security_shift_id' => $shifts['generated']->id,
                'round_number' => 2,
            ],
            [
                'recorded_by_user_id' => $nocheroUser->id,
                'recorded_at' => '2026-06-26 02:40:00',
                'overall_status' => SecurityRound::STATUS_REQUIERE_ATENCION,
                'observations' => 'Se constata fuga de agua en pasillo norte de laboratorio de ciencias.',
                'nochero_confirmation_name' => $shifts['generated']->staff?->full_name ?: $nocheroUser->name,
                'signature_data' => '{"seeded":true,"signed":true}',
                'latitude' => -39.8140500,
                'longitude' => -73.2453500,
                'location_accuracy' => 5.2,
                'act_number' => sprintf('ACTA-RS-20260626-%04d-02', $shifts['generated']->id),
                'act_generated_at' => '2026-06-26 02:42:00',
            ],
        );

        $activeRound = SecurityRound::query()->updateOrCreate(
            [
                'security_shift_id' => $shifts['active']->id,
                'round_number' => 1,
            ],
            [
                'recorded_by_user_id' => $nocheroUser->id,
                'recorded_at' => '2026-06-27 23:20:00',
                'overall_status' => SecurityRound::STATUS_SIN_NOVEDAD,
                'observations' => 'Primera ronda del turno activo sin hallazgos relevantes.',
                'nochero_confirmation_name' => $shifts['active']->staff?->full_name ?: $nocheroUser->name,
                'signature_data' => '{"seeded":true,"signed":true}',
                'latitude' => -39.8141600,
                'longitude' => -73.2452600,
                'location_accuracy' => 7.1,
                'act_number' => sprintf('ACTA-RS-20260627-%04d-01', $shifts['active']->id),
                'act_generated_at' => '2026-06-27 23:22:00',
            ],
        );

        $roundOneSectors = $this->seedRoundSectors($roundOne, [
            ['dependency' => 'DEP-010', 'sector_name' => 'Auditorio', 'state' => 'sin_novedad', 'observations' => 'Accesos sin novedad.'],
            ['dependency' => 'DEP-003', 'sector_name' => 'Laboratorio de computación', 'state' => 'observado', 'observations' => 'Puerta lateral sin pestillo.'],
            ['dependency' => 'DEP-007', 'sector_name' => 'Gimnasio', 'state' => 'sin_novedad', 'observations' => 'Sin hallazgos.'],
        ]);

        $roundTwoSectors = $this->seedRoundSectors($roundTwo, [
            ['dependency' => 'DEP-004', 'sector_name' => 'Laboratorio de ciencias', 'state' => 'incidente', 'observations' => 'Agua visible en pasillo norte.'],
            ['dependency' => 'DEP-015', 'sector_name' => 'Comedor', 'state' => 'sin_novedad', 'observations' => 'Sin hallazgos.'],
        ]);

        $this->seedRoundSectors($activeRound, [
            ['dependency' => 'DEP-010', 'sector_name' => 'Auditorio', 'state' => 'sin_novedad', 'observations' => 'Cerrado y asegurado.'],
            ['dependency' => 'DEP-001', 'sector_name' => 'Sala de conferencias', 'state' => 'sin_novedad', 'observations' => 'Sin observaciones.'],
        ]);

        $incidentOne = SecurityIncident::query()->updateOrCreate(
            [
                'security_round_id' => $roundOne->id,
                'title' => 'Puerta lateral abierta en laboratorio',
            ],
            [
                'security_shift_id' => $shifts['generated']->id,
                'security_round_sector_id' => $roundOneSectors[1]->id,
                'reported_by_user_id' => $nocheroUser->id,
                'status_id' => $statusResolved->id,
                'maintenance_dependency_id' => $this->dependency('DEP-003')->id,
                'current_responsible_user_id' => $sergioUser->id,
                'priority' => SecurityIncident::PRIORITY_MEDIA,
                'description' => 'La puerta lateral del laboratorio quedó sin seguro al cierre de la jornada.',
                'sector_name' => 'Laboratorio de computación',
                'requires_immediate_attention' => false,
                'response_due_at' => '2026-06-26 23:15:00',
                'responded_at' => '2026-06-25 23:40:00',
                'resolved_at' => '2026-06-26 00:05:00',
                'response_summary' => 'Inspectoría verificó cierre y solicitó ajuste menor de pestillo.',
            ],
        );

        $incidentTwo = SecurityIncident::query()->updateOrCreate(
            [
                'security_round_id' => $roundTwo->id,
                'title' => 'Fuga de agua en pasillo norte',
            ],
            [
                'security_shift_id' => $shifts['generated']->id,
                'security_round_sector_id' => $roundTwoSectors[0]->id,
                'reported_by_user_id' => $nocheroUser->id,
                'status_id' => $statusDerived->id,
                'maintenance_dependency_id' => $this->dependency('DEP-004')->id,
                'current_responsible_user_id' => $ricardoUser->id,
                'priority' => SecurityIncident::PRIORITY_CRITICA,
                'description' => 'Se detecta fuga activa de agua que compromete tránsito y laboratorio contiguo.',
                'sector_name' => 'Laboratorio de ciencias',
                'requires_immediate_attention' => true,
                'response_due_at' => '2026-06-26 03:40:00',
                'responded_at' => '2026-06-26 02:55:00',
                'alert_sent_at' => '2026-06-26 02:45:00',
                'response_summary' => 'Derivada a mantención para contención inmediata.',
            ],
        );

        SecurityIncidentAssignment::query()->updateOrCreate(
            [
                'security_incident_id' => $incidentOne->id,
                'user_id' => $sergioUser->id,
                'notes' => 'Responsable principal',
            ],
            [
                'assigned_by_user_id' => $nocheroUser->id,
                'assigned_at' => '2026-06-25 23:30:00',
                'released_at' => '2026-06-26 00:05:00',
                'is_current' => false,
            ],
        );

        SecurityIncidentAssignment::query()->updateOrCreate(
            [
                'security_incident_id' => $incidentTwo->id,
                'user_id' => $ricardoUser->id,
                'notes' => 'Responsable principal',
            ],
            [
                'assigned_by_user_id' => $nocheroUser->id,
                'assigned_at' => '2026-06-26 02:45:00',
                'released_at' => null,
                'is_current' => true,
            ],
        );

        SecurityIncidentComment::query()->updateOrCreate(
            [
                'security_incident_id' => $incidentOne->id,
                'user_id' => $sergioUser->id,
                'comment' => 'Se verifica cierre del acceso y se deja observación para ajuste del pestillo.',
            ],
            [
                'status_id' => $statusReview->id,
                'assigned_to_user_id' => $sergioUser->id,
                'responded_at' => '2026-06-25 23:40:00',
                'is_internal' => false,
            ],
        );

        SecurityIncidentComment::query()->updateOrCreate(
            [
                'security_incident_id' => $incidentOne->id,
                'user_id' => $sergioUser->id,
                'comment' => 'Incidente resuelto tras asegurar acceso y confirmar continuidad operativa.',
            ],
            [
                'status_id' => $statusResolved->id,
                'assigned_to_user_id' => null,
                'responded_at' => '2026-06-26 00:05:00',
                'is_internal' => false,
            ],
        );

        SecurityIncidentComment::query()->updateOrCreate(
            [
                'security_incident_id' => $incidentTwo->id,
                'user_id' => $ricardoUser->id,
                'comment' => 'Se deriva cuadrilla y se aísla el sector mientras se contiene la fuga.',
            ],
            [
                'status_id' => $statusDerived->id,
                'assigned_to_user_id' => $ricardoUser->id,
                'responded_at' => '2026-06-26 02:55:00',
                'is_internal' => false,
            ],
        );

        SecurityNotification::query()->updateOrCreate(
            [
                'user_id' => $ricardoUser->id,
                'security_incident_id' => $incidentTwo->id,
                'title' => 'Incidente crítico derivado a mantención',
            ],
            [
                'message' => 'Existe una fuga de agua crítica en laboratorio de ciencias que requiere contención inmediata.',
                'priority' => 'critica',
                'action_url' => '/security/incidents',
                'read_at' => null,
                'sent_via_mail_at' => '2026-06-26 02:46:00',
            ],
        );

        SecurityNotification::query()->updateOrCreate(
            [
                'user_id' => $carolinaUser->id,
                'security_incident_id' => $incidentTwo->id,
                'title' => 'Alerta crítica de seguridad',
            ],
            [
                'message' => 'Se registró una fuga de agua crítica en laboratorio de ciencias durante la ronda nocturna.',
                'priority' => 'critica',
                'action_url' => '/security/incidents',
                'read_at' => null,
                'sent_via_mail_at' => '2026-06-26 02:47:00',
            ],
        );

        SecurityNotification::query()->updateOrCreate(
            [
                'user_id' => $sergioUser->id,
                'security_incident_id' => $incidentOne->id,
                'title' => 'Novedad resuelta en laboratorio',
            ],
            [
                'message' => 'La puerta lateral del laboratorio fue asegurada y la novedad quedó resuelta.',
                'priority' => 'media',
                'action_url' => '/security/incidents',
                'read_at' => '2026-06-26 08:10:00',
                'sent_via_mail_at' => null,
            ],
        );
    }

    /**
     * @param  array<int, array{dependency:string, sector_name:string, state:string, observations:?string}>  $definitions
     * @return array<int, \App\Models\Security\SecurityRoundSector>
     */
    private function seedRoundSectors(SecurityRound $round, array $definitions): array
    {
        $records = [];

        foreach ($definitions as $index => $definition) {
            $records[] = SecurityRoundSector::query()->updateOrCreate(
                [
                    'security_round_id' => $round->id,
                    'display_order' => $index + 1,
                ],
                [
                    'maintenance_dependency_id' => $this->dependency($definition['dependency'])->id,
                    'sector_name' => $definition['sector_name'],
                    'sector_state' => $definition['state'],
                    'observations' => $definition['observations'],
                ],
            );
        }

        return $records;
    }

    private function incidentStatus(string $code): SecurityIncidentStatus
    {
        return SecurityIncidentStatus::query()->where('code', $code)->firstOrFail();
    }
}
