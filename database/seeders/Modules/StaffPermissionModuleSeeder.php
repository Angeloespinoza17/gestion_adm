<?php

namespace Database\Seeders\Modules;

use App\Models\PermissionRequest;
use App\Models\PermissionRequestApproval;
use App\Models\PermissionRequestLog;
use App\Models\PermissionRequestReplacement;
use App\Models\PermissionRequestWatcher;
use App\Models\PermissionType;
use App\Models\PermissionTypeWatcher;
use App\Models\Staff;
use App\Models\StaffPermissionWatcher;
use App\Models\User;
use Database\Seeders\Support\ModuleSeeder;

class StaffPermissionModuleSeeder extends ModuleSeeder
{
    public function run(): void
    {
        $this->seedTypeWatchers();
        $this->seedStaffWatchers();
        $this->seedRequests();
    }

    private function seedTypeWatchers(): void
    {
        $directionUser = $this->user('carolina.munoz@cnscgestion.local');
        $hrUser = $this->user('marcelo.rojas@cnscgestion.local');

        PermissionType::query()->where('active', true)->each(function (PermissionType $type) use ($directionUser, $hrUser) {
            PermissionTypeWatcher::query()->updateOrCreate(
                ['permission_type_id' => $type->id, 'target_type' => 'manager', 'role_id' => null, 'user_id' => null],
                ['notify' => true, 'can_view' => true, 'active' => true],
            );

            PermissionTypeWatcher::query()->updateOrCreate(
                ['permission_type_id' => $type->id, 'target_type' => 'hr', 'role_id' => null, 'user_id' => null],
                ['notify' => true, 'can_view' => true, 'active' => true],
            );

            PermissionTypeWatcher::query()->updateOrCreate(
                ['permission_type_id' => $type->id, 'target_type' => 'user', 'role_id' => null, 'user_id' => $hrUser->id],
                ['notify' => false, 'can_view' => true, 'active' => true],
            );

            if ($type->requires_direction_approval) {
                PermissionTypeWatcher::query()->updateOrCreate(
                    ['permission_type_id' => $type->id, 'target_type' => 'direction', 'role_id' => null, 'user_id' => null],
                    ['notify' => true, 'can_view' => true, 'active' => true],
                );

                PermissionTypeWatcher::query()->updateOrCreate(
                    ['permission_type_id' => $type->id, 'target_type' => 'user', 'role_id' => null, 'user_id' => $directionUser->id],
                    ['notify' => false, 'can_view' => true, 'active' => true],
                );
            }
        });
    }

    private function seedStaffWatchers(): void
    {
        $definitions = [
            [
                'staff' => 'andrea.medina@cnscgestion.local',
                'watchers' => [
                    ['target_type' => 'manager'],
                    ['target_type' => 'hr'],
                    ['target_type' => 'user', 'user' => 'carolina.munoz@cnscgestion.local', 'notify' => false, 'can_view' => true],
                ],
            ],
            [
                'staff' => 'daniela.castillo@cnscgestion.local',
                'watchers' => [
                    ['target_type' => 'manager'],
                    ['target_type' => 'hr'],
                    ['target_type' => 'user', 'user' => 'carolina.munoz@cnscgestion.local', 'notify' => false, 'can_view' => true],
                ],
            ],
            [
                'staff' => 'paula.vargas@cnscgestion.local',
                'watchers' => [
                    ['target_type' => 'direction'],
                    ['target_type' => 'hr'],
                ],
            ],
            [
                'staff' => 'laura.diaz@cnscgestion.local',
                'watchers' => [
                    ['target_type' => 'manager'],
                    ['target_type' => 'user', 'user' => 'sergio.torres@cnscgestion.local'],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $staff = $this->staffByEmail($definition['staff']);

            foreach ($definition['watchers'] as $watcher) {
                $userId = isset($watcher['user']) ? $this->user($watcher['user'])->id : null;

                StaffPermissionWatcher::query()->updateOrCreate(
                    [
                        'staff_id' => $staff->id,
                        'target_type' => $watcher['target_type'],
                        'role_id' => null,
                        'user_id' => $userId,
                    ],
                    [
                        'notify' => $watcher['notify'] ?? true,
                        'can_view' => $watcher['can_view'] ?? true,
                        'active' => true,
                    ],
                );
            }
        }
    }

    private function seedRequests(): void
    {
        $requests = [
            [
                'staff' => 'andrea.medina@cnscgestion.local',
                'type' => 'Permiso administrativo',
                'reason' => 'Asistencia a trámite institucional externo',
                'description' => 'Presentación de antecedentes curriculares en universidad asociada.',
                'start_date' => '2026-06-12',
                'end_date' => '2026-06-12',
                'duration_days' => 1,
                'duration_label' => '1 día',
                'is_full_day' => true,
                'with_pay' => true,
                'affects_salary' => false,
                'requires_replacement' => false,
                'status' => 'ejecutado',
                'attendance_status' => 'ausencia_autorizada',
                'payroll_status' => 'no_aplica',
                'submitted_at' => '2026-06-01 08:10:00',
                'approved_at' => '2026-06-01 10:40:00',
                'executed_at' => '2026-06-12 18:00:00',
                'approvals' => [
                    ['step' => 'manager', 'user' => 'paula.vargas@cnscgestion.local', 'status' => 'aprobado', 'comments' => 'Cobertura interna confirmada.', 'acted_at' => '2026-06-01 09:00:00'],
                    ['step' => 'hr', 'user' => 'marcelo.rojas@cnscgestion.local', 'status' => 'aprobado', 'comments' => 'Sin observaciones administrativas.', 'acted_at' => '2026-06-01 10:30:00'],
                ],
                'logs' => [
                    ['action' => 'creada', 'old_status' => null, 'new_status' => 'borrador', 'user' => 'andrea.medina@cnscgestion.local', 'created_at' => '2026-06-01 07:55:00'],
                    ['action' => 'enviada_revision', 'old_status' => 'borrador', 'new_status' => 'pendiente_jefatura', 'user' => 'andrea.medina@cnscgestion.local', 'created_at' => '2026-06-01 08:10:00'],
                    ['action' => 'aprobada_etapa', 'old_status' => 'pendiente_jefatura', 'new_status' => 'pendiente_rrhh', 'user' => 'paula.vargas@cnscgestion.local', 'created_at' => '2026-06-01 09:00:00'],
                    ['action' => 'aprobada_etapa', 'old_status' => 'pendiente_rrhh', 'new_status' => 'aprobado', 'user' => 'marcelo.rojas@cnscgestion.local', 'created_at' => '2026-06-01 10:30:00'],
                    ['action' => 'ejecutada', 'old_status' => 'aprobado', 'new_status' => 'ejecutado', 'user' => 'marcelo.rojas@cnscgestion.local', 'created_at' => '2026-06-12 18:00:00'],
                ],
            ],
            [
                'staff' => 'paula.vargas@cnscgestion.local',
                'type' => 'Permiso por capacitación',
                'reason' => 'Participación en seminario de liderazgo pedagógico',
                'description' => 'Capacitación de dos jornadas en innovación curricular.',
                'start_date' => '2026-07-15',
                'end_date' => '2026-07-16',
                'duration_days' => 2,
                'duration_label' => '2 días',
                'is_full_day' => true,
                'with_pay' => true,
                'affects_salary' => false,
                'requires_replacement' => true,
                'status' => 'pendiente_rrhh',
                'current_step' => 'hr',
                'attendance_status' => 'pendiente',
                'payroll_status' => 'no_aplica',
                'submitted_at' => '2026-06-20 08:20:00',
                'approvals' => [
                    ['step' => 'manager', 'user' => 'carolina.munoz@cnscgestion.local', 'status' => 'aprobado', 'comments' => 'Se autoriza salida institucional.', 'acted_at' => '2026-06-20 09:00:00'],
                    ['step' => 'direction', 'user' => 'carolina.munoz@cnscgestion.local', 'status' => 'aprobado', 'comments' => 'Aprobado por dirección.', 'acted_at' => '2026-06-20 11:00:00'],
                ],
                'replacements' => [
                    [
                        'replacement_staff' => 'daniela.castillo@cnscgestion.local',
                        'course_name' => '7° básico A',
                        'subject_name' => 'Ciencias',
                        'dependency_name' => 'Sala de conferencias',
                        'schedule_detail' => 'Bloques 1 al 4 de ambas jornadas.',
                        'start_datetime' => '2026-07-15 08:00:00',
                        'end_datetime' => '2026-07-16 16:00:00',
                        'status' => 'coordinado',
                        'observations' => 'Cobertura acordada con coordinación académica.',
                    ],
                ],
                'logs' => [
                    ['action' => 'creada', 'old_status' => null, 'new_status' => 'borrador', 'user' => 'paula.vargas@cnscgestion.local', 'created_at' => '2026-06-20 07:50:00'],
                    ['action' => 'enviada_revision', 'old_status' => 'borrador', 'new_status' => 'pendiente_jefatura', 'user' => 'paula.vargas@cnscgestion.local', 'created_at' => '2026-06-20 08:20:00'],
                    ['action' => 'aprobada_etapa', 'old_status' => 'pendiente_jefatura', 'new_status' => 'pendiente_direccion', 'user' => 'carolina.munoz@cnscgestion.local', 'created_at' => '2026-06-20 09:00:00'],
                    ['action' => 'aprobada_etapa', 'old_status' => 'pendiente_direccion', 'new_status' => 'pendiente_rrhh', 'user' => 'carolina.munoz@cnscgestion.local', 'created_at' => '2026-06-20 11:00:00'],
                ],
            ],
            [
                'staff' => 'daniela.castillo@cnscgestion.local',
                'type' => 'Permiso por emergencia',
                'reason' => 'Emergencia familiar con traslado a centro asistencial',
                'description' => 'Solicitud urgente por hospitalización de familiar directo.',
                'start_date' => '2026-07-03',
                'end_date' => '2026-07-04',
                'duration_days' => 2,
                'duration_label' => '2 días',
                'is_full_day' => true,
                'with_pay' => true,
                'affects_salary' => false,
                'requires_replacement' => true,
                'status' => 'pendiente_direccion',
                'current_step' => 'direction',
                'attendance_status' => 'pendiente',
                'payroll_status' => 'no_aplica',
                'urgency' => true,
                'submitted_at' => '2026-06-25 11:15:00',
                'approvals' => [
                    ['step' => 'manager', 'user' => 'paula.vargas@cnscgestion.local', 'status' => 'aprobado', 'comments' => 'Se deja reemplazo sugerido.', 'acted_at' => '2026-06-25 12:05:00'],
                ],
                'replacements' => [
                    [
                        'replacement_staff' => 'andrea.medina@cnscgestion.local',
                        'course_name' => '8° básico B',
                        'subject_name' => 'Matemática',
                        'dependency_name' => 'Sala de conferencias',
                        'schedule_detail' => 'Cobertura parcial de bloques 1 a 3.',
                        'start_datetime' => '2026-07-03 08:00:00',
                        'end_datetime' => '2026-07-04 13:00:00',
                        'status' => 'pendiente',
                    ],
                ],
                'logs' => [
                    ['action' => 'creada', 'old_status' => null, 'new_status' => 'borrador', 'user' => 'daniela.castillo@cnscgestion.local', 'created_at' => '2026-06-25 10:50:00'],
                    ['action' => 'enviada_revision', 'old_status' => 'borrador', 'new_status' => 'pendiente_jefatura', 'user' => 'daniela.castillo@cnscgestion.local', 'created_at' => '2026-06-25 11:15:00'],
                    ['action' => 'aprobada_etapa', 'old_status' => 'pendiente_jefatura', 'new_status' => 'pendiente_direccion', 'user' => 'paula.vargas@cnscgestion.local', 'created_at' => '2026-06-25 12:05:00'],
                ],
            ],
            [
                'staff' => 'laura.diaz@cnscgestion.local',
                'type' => 'Permiso por trámite',
                'reason' => 'Control bancario personal',
                'description' => 'Salida breve durante la mañana.',
                'start_date' => '2026-06-21',
                'end_date' => '2026-06-21',
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'duration_hours' => 2,
                'duration_days' => 0.25,
                'duration_label' => '2 horas',
                'is_full_day' => false,
                'is_half_day' => false,
                'with_pay' => true,
                'affects_salary' => false,
                'requires_replacement' => false,
                'status' => 'rechazado',
                'attendance_status' => 'pendiente_regularizacion',
                'payroll_status' => 'no_aplica',
                'requires_regularization' => true,
                'submitted_at' => '2026-06-18 09:00:00',
                'rejected_at' => '2026-06-19 16:20:00',
                'visible_observations' => 'No existe cobertura en portería para el horario solicitado.',
                'internal_observations' => 'Coordinar nueva fecha con inspectoría.',
                'approvals' => [
                    ['step' => 'manager', 'user' => 'sergio.torres@cnscgestion.local', 'status' => 'rechazado', 'comments' => 'No existe reemplazo disponible.', 'internal_comments' => 'Evitar coincidir con ingreso de visitas.', 'acted_at' => '2026-06-19 16:20:00'],
                ],
                'logs' => [
                    ['action' => 'creada', 'old_status' => null, 'new_status' => 'borrador', 'user' => 'laura.diaz@cnscgestion.local', 'created_at' => '2026-06-18 08:40:00'],
                    ['action' => 'enviada_revision', 'old_status' => 'borrador', 'new_status' => 'pendiente_jefatura', 'user' => 'laura.diaz@cnscgestion.local', 'created_at' => '2026-06-18 09:00:00'],
                    ['action' => 'rechazada', 'old_status' => 'pendiente_jefatura', 'new_status' => 'rechazado', 'user' => 'sergio.torres@cnscgestion.local', 'created_at' => '2026-06-19 16:20:00'],
                ],
            ],
            [
                'staff' => 'jose.campos@cnscgestion.local',
                'type' => 'Permiso médico',
                'reason' => 'Control médico preventivo',
                'description' => 'Se requiere documento complementario para respaldo.',
                'start_date' => '2026-06-24',
                'end_date' => '2026-06-24',
                'duration_days' => 1,
                'duration_label' => '1 día',
                'is_full_day' => true,
                'with_pay' => true,
                'affects_salary' => false,
                'requires_replacement' => false,
                'status' => 'observado',
                'attendance_status' => 'pendiente',
                'payroll_status' => 'no_aplica',
                'submitted_at' => '2026-06-22 17:10:00',
                'visible_observations' => 'Adjuntar certificado o comprobante de atención.',
                'internal_observations' => 'No se recibió documento al cierre del turno.',
                'approvals' => [
                    ['step' => 'manager', 'user' => 'sergio.torres@cnscgestion.local', 'status' => 'observado', 'comments' => 'Falta respaldo documental.', 'internal_comments' => 'Coordinar regularización con RRHH.', 'acted_at' => '2026-06-23 08:45:00'],
                ],
                'logs' => [
                    ['action' => 'creada', 'old_status' => null, 'new_status' => 'borrador', 'user' => 'jose.campos@cnscgestion.local', 'created_at' => '2026-06-22 16:55:00'],
                    ['action' => 'enviada_revision', 'old_status' => 'borrador', 'new_status' => 'pendiente_jefatura', 'user' => 'jose.campos@cnscgestion.local', 'created_at' => '2026-06-22 17:10:00'],
                    ['action' => 'observada', 'old_status' => 'pendiente_jefatura', 'new_status' => 'observado', 'user' => 'sergio.torres@cnscgestion.local', 'created_at' => '2026-06-23 08:45:00'],
                ],
            ],
            [
                'staff' => 'ricardo.fuentes@cnscgestion.local',
                'type' => 'Permiso sin goce de remuneraciones',
                'reason' => 'Asuntos familiares de larga duración',
                'description' => 'Solicitud aprobada con descuento de remuneraciones asociado.',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-05',
                'duration_days' => 5,
                'duration_label' => '5 días',
                'is_full_day' => true,
                'with_pay' => false,
                'affects_salary' => true,
                'requires_replacement' => true,
                'status' => 'aprobado',
                'attendance_status' => 'ausencia_autorizada',
                'payroll_status' => 'descuento_pendiente',
                'salary_discount_days' => 5,
                'submitted_at' => '2026-07-10 09:30:00',
                'approved_at' => '2026-07-10 15:00:00',
                'approvals' => [
                    ['step' => 'manager', 'user' => 'carolina.munoz@cnscgestion.local', 'status' => 'aprobado', 'comments' => 'Se reorganiza equipo de mantención.', 'acted_at' => '2026-07-10 10:00:00'],
                    ['step' => 'direction', 'user' => 'carolina.munoz@cnscgestion.local', 'status' => 'aprobado', 'comments' => 'Autorización de dirección.', 'acted_at' => '2026-07-10 11:00:00'],
                    ['step' => 'hr', 'user' => 'marcelo.rojas@cnscgestion.local', 'status' => 'aprobado', 'comments' => 'Aplicar descuento en remuneraciones.', 'acted_at' => '2026-07-10 14:45:00'],
                ],
                'logs' => [
                    ['action' => 'creada', 'old_status' => null, 'new_status' => 'borrador', 'user' => 'ricardo.fuentes@cnscgestion.local', 'created_at' => '2026-07-10 08:55:00'],
                    ['action' => 'enviada_revision', 'old_status' => 'borrador', 'new_status' => 'pendiente_jefatura', 'user' => 'ricardo.fuentes@cnscgestion.local', 'created_at' => '2026-07-10 09:30:00'],
                    ['action' => 'aprobada_etapa', 'old_status' => 'pendiente_jefatura', 'new_status' => 'pendiente_direccion', 'user' => 'carolina.munoz@cnscgestion.local', 'created_at' => '2026-07-10 10:00:00'],
                    ['action' => 'aprobada_etapa', 'old_status' => 'pendiente_direccion', 'new_status' => 'pendiente_rrhh', 'user' => 'carolina.munoz@cnscgestion.local', 'created_at' => '2026-07-10 11:00:00'],
                    ['action' => 'aprobada_etapa', 'old_status' => 'pendiente_rrhh', 'new_status' => 'aprobado', 'user' => 'marcelo.rojas@cnscgestion.local', 'created_at' => '2026-07-10 14:45:00'],
                ],
            ],
            [
                'staff' => 'marcelo.rojas@cnscgestion.local',
                'type' => 'Permiso personal',
                'reason' => 'Trámite personal pendiente',
                'description' => 'Borrador aún no enviado a revisión.',
                'start_date' => '2026-09-02',
                'end_date' => '2026-09-02',
                'duration_days' => 0.5,
                'duration_label' => 'Media jornada',
                'is_full_day' => false,
                'is_half_day' => true,
                'with_pay' => true,
                'affects_salary' => false,
                'requires_replacement' => false,
                'status' => 'borrador',
                'attendance_status' => 'pendiente',
                'payroll_status' => 'no_aplica',
                'employee_observations' => 'Pendiente confirmar horario final.',
                'logs' => [
                    ['action' => 'creada', 'old_status' => null, 'new_status' => 'borrador', 'user' => 'marcelo.rojas@cnscgestion.local', 'created_at' => '2026-08-25 17:30:00'],
                ],
            ],
        ];

        foreach ($requests as $definition) {
            $staff = $this->staffByEmail($definition['staff']);
            $user = $this->user($definition['staff']);
            $type = $this->permissionType($definition['type']);
            $managerUser = $this->resolveManagerUser($staff);

            $request = PermissionRequest::query()->updateOrCreate(
                [
                    'staff_id' => $staff->id,
                    'permission_type_id' => $type->id,
                    'start_date' => $definition['start_date'],
                    'reason' => $definition['reason'],
                ],
                [
                    'requested_by_user_id' => $user->id,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'direct_manager_user_id' => $managerUser?->id,
                    'cargo_name' => $staff->cargo?->name,
                    'direct_manager_name' => $managerUser?->staff?->full_name ?: $managerUser?->name,
                    'end_date' => $definition['end_date'],
                    'start_time' => $definition['start_time'] ?? null,
                    'end_time' => $definition['end_time'] ?? null,
                    'duration_hours' => $definition['duration_hours'] ?? null,
                    'duration_days' => $definition['duration_days'] ?? null,
                    'duration_label' => $definition['duration_label'] ?? null,
                    'is_full_day' => $definition['is_full_day'] ?? false,
                    'is_half_day' => $definition['is_half_day'] ?? false,
                    'with_pay' => $definition['with_pay'],
                    'affects_salary' => $definition['affects_salary'],
                    'affects_attendance' => $definition['affects_attendance'] ?? true,
                    'requires_replacement' => $definition['requires_replacement'],
                    'reason' => $definition['reason'],
                    'description' => $definition['description'] ?? null,
                    'employee_observations' => $definition['employee_observations'] ?? null,
                    'visible_observations' => $definition['visible_observations'] ?? null,
                    'internal_observations' => $definition['internal_observations'] ?? null,
                    'status' => $definition['status'],
                    'current_step' => $definition['current_step'] ?? null,
                    'urgency' => $definition['urgency'] ?? false,
                    'retroactive' => $definition['retroactive'] ?? false,
                    'attendance_status' => $definition['attendance_status'],
                    'payroll_status' => $definition['payroll_status'],
                    'salary_discount_hours' => $definition['salary_discount_hours'] ?? null,
                    'salary_discount_days' => $definition['salary_discount_days'] ?? null,
                    'requires_regularization' => $definition['requires_regularization'] ?? false,
                    'submitted_at' => $definition['submitted_at'] ?? null,
                    'approved_at' => $definition['approved_at'] ?? null,
                    'rejected_at' => $definition['rejected_at'] ?? null,
                    'cancelled_at' => $definition['cancelled_at'] ?? null,
                    'executed_at' => $definition['executed_at'] ?? null,
                ],
            );

            $request->departments()->sync($staff->departments()->pluck('departments.id')->all());

            foreach ($definition['approvals'] ?? [] as $approval) {
                PermissionRequestApproval::query()->updateOrCreate(
                    [
                        'permission_request_id' => $request->id,
                        'approver_user_id' => $this->user($approval['user'])->id,
                        'role_or_step' => $approval['step'],
                    ],
                    [
                        'status' => $approval['status'],
                        'comments' => $approval['comments'] ?? null,
                        'internal_comments' => $approval['internal_comments'] ?? null,
                        'acted_at' => $approval['acted_at'] ?? null,
                    ],
                );
            }

            foreach ($definition['replacements'] ?? [] as $replacement) {
                PermissionRequestReplacement::query()->updateOrCreate(
                    [
                        'permission_request_id' => $request->id,
                        'course_name' => $replacement['course_name'] ?? null,
                        'start_datetime' => $replacement['start_datetime'],
                    ],
                    [
                        'replaced_staff_id' => $staff->id,
                        'replacement_staff_id' => isset($replacement['replacement_staff']) ? $this->staffByEmail($replacement['replacement_staff'])->id : null,
                        'subject_name' => $replacement['subject_name'] ?? null,
                        'dependency_name' => $replacement['dependency_name'] ?? null,
                        'schedule_detail' => $replacement['schedule_detail'] ?? null,
                        'end_datetime' => $replacement['end_datetime'],
                        'status' => $replacement['status'],
                        'observations' => $replacement['observations'] ?? null,
                    ],
                );
            }

            foreach ($definition['logs'] ?? [] as $log) {
                PermissionRequestLog::query()->updateOrCreate(
                    [
                        'permission_request_id' => $request->id,
                        'action' => $log['action'],
                        'created_at' => $log['created_at'],
                    ],
                    [
                        'user_id' => $this->user($log['user'])->id,
                        'old_status' => $log['old_status'],
                        'new_status' => $log['new_status'],
                        'details' => [
                            'seeded' => true,
                            'reason' => $definition['reason'],
                        ],
                    ],
                );
            }

            $this->syncRequestWatchers($request, $staff, $type, $managerUser);
        }
    }

    private function resolveManagerUser(Staff $staff): ?User
    {
        $relation = $staff->organigramRelations()
            ->where('relationship_type', 'direct_manager')
            ->where('active', true)
            ->orderByDesc('is_primary')
            ->orderBy('priority')
            ->first();

        return $relation?->relatedStaff?->user;
    }

    private function syncRequestWatchers(PermissionRequest $request, Staff $staff, PermissionType $type, ?User $managerUser): void
    {
        $watchers = [];
        $hrUser = $this->user('marcelo.rojas@cnscgestion.local');
        $directionUser = $this->user('carolina.munoz@cnscgestion.local');

        if ($managerUser) {
            $watchers[$managerUser->id] = [
                'permission_type_watcher_id' => $this->typeWatcherId($type, 'manager'),
                'staff_permission_watcher_id' => $this->staffWatcherId($staff, 'manager'),
                'source_type' => 'manager',
                'source_label' => 'Jefatura directa',
                'notify' => true,
                'can_view' => true,
            ];
        }

        $watchers[$hrUser->id] = [
            'permission_type_watcher_id' => $this->typeWatcherId($type, 'hr'),
            'staff_permission_watcher_id' => $this->staffWatcherId($staff, 'hr'),
            'source_type' => 'hr',
            'source_label' => 'RRHH / Administración',
            'notify' => true,
            'can_view' => true,
        ];

        if ($type->requires_direction_approval || $this->staffWatcherId($staff, 'user', $directionUser->id)) {
            $watchers[$directionUser->id] = [
                'permission_type_watcher_id' => $this->typeWatcherId($type, 'direction') ?: $this->typeWatcherId($type, 'user', $directionUser->id),
                'staff_permission_watcher_id' => $this->staffWatcherId($staff, 'user', $directionUser->id),
                'source_type' => 'direction',
                'source_label' => 'Dirección',
                'notify' => true,
                'can_view' => true,
            ];
        }

        PermissionRequestWatcher::query()->where('permission_request_id', $request->id)->delete();

        foreach ($watchers as $userId => $watcher) {
            PermissionRequestWatcher::query()->updateOrCreate(
                [
                    'permission_request_id' => $request->id,
                    'user_id' => $userId,
                ],
                [
                    'permission_type_watcher_id' => $watcher['permission_type_watcher_id'],
                    'staff_permission_watcher_id' => $watcher['staff_permission_watcher_id'],
                    'source_type' => $watcher['source_type'],
                    'source_label' => $watcher['source_label'],
                    'notify' => $watcher['notify'],
                    'can_view' => $watcher['can_view'],
                    'notified_at' => null,
                ],
            );
        }
    }

    private function typeWatcherId(PermissionType $type, string $targetType, ?int $userId = null): ?int
    {
        return PermissionTypeWatcher::query()
            ->where('permission_type_id', $type->id)
            ->where('target_type', $targetType)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->value('id');
    }

    private function staffWatcherId(Staff $staff, string $targetType, ?int $userId = null): ?int
    {
        return StaffPermissionWatcher::query()
            ->where('staff_id', $staff->id)
            ->where('target_type', $targetType)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->value('id');
    }
}
