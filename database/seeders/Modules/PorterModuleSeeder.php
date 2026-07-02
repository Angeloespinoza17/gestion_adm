<?php

namespace Database\Seeders\Modules;

use App\Models\PorterGoodsMovement;
use App\Models\PorterReceivedItem;
use App\Models\PorterStudentWithdrawal;
use App\Models\StudentProfile;
use Database\Seeders\Support\ModuleSeeder;

class PorterModuleSeeder extends ModuleSeeder
{
    public function run(): void
    {
        $this->seedStudentContext();
        $this->seedWithdrawals();
        $this->seedReceivedItems();
        $this->seedGoodsMovements();
    }

    private function seedStudentContext(): void
    {
        StudentProfile::query()->where('rut', '15555555-1')->update([
            'pickup_restriction' => true,
            'pickup_restriction_notes' => 'Retiro solo con apoderado registrado o autorización expresa de inspectoría.',
            'porter_alert_notes' => 'Registrar observación si el retiro ocurre antes de las 13:00 horas.',
        ]);
    }

    private function seedWithdrawals(): void
    {
        $definitions = [
            [
                'student_rut' => '15555555-1',
                'status' => 'autorizado',
                'withdrawn_at' => '2026-06-18 13:10:00',
                'person_name' => 'Ana Soto',
                'person_rut' => '12222333-4',
                'person_relationship' => 'madre',
                'person_phone' => '+56920000001',
                'reason' => 'medico',
                'observations' => 'Retiro anticipado por control dental.',
                'person_authorized' => true,
                'authorization_source' => 'registro_apoderado',
                'requires_special_authorization' => false,
                'registered_by' => 'laura.diaz@cnscgestion.local',
                'authorized_by' => 'sergio.torres@cnscgestion.local',
                'logs' => [
                    ['action' => 'registro_retiro', 'from' => null, 'to' => 'registrado', 'description' => 'Retiro registrado en portería.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-18 13:10:00'],
                    ['action' => 'autoriza_retiro', 'from' => 'registrado', 'to' => 'autorizado', 'description' => 'Retiro autorizado por inspectoría.', 'performed_by' => 'sergio.torres@cnscgestion.local', 'performed_at' => '2026-06-18 13:15:00'],
                ],
            ],
            [
                'student_rut' => '16666666-2',
                'status' => 'observado',
                'withdrawn_at' => '2026-06-19 11:45:00',
                'person_name' => 'Carlos Díaz',
                'person_relationship' => 'familiar',
                'person_phone' => '+56921110002',
                'reason' => 'familiar',
                'observations' => 'Solicitante no figura en lista de retiro habitual.',
                'person_authorized' => false,
                'authorization_source' => 'alerta_porteria',
                'requires_special_authorization' => true,
                'authorization_notes' => 'Se solicita confirmación con apoderado titular.',
                'registered_by' => 'laura.diaz@cnscgestion.local',
                'authorization_request' => [
                    'status' => 'pendiente',
                    'required_permission_slug' => 'autorizar_retiros_porteria',
                    'reason' => 'Retiro no autorizado de forma preventiva.',
                    'requested_by' => 'laura.diaz@cnscgestion.local',
                    'requested_at' => '2026-06-19 11:46:00',
                    'payload' => ['channel' => 'porter', 'student_rut' => '16666666-2'],
                ],
                'logs' => [
                    ['action' => 'registro_retiro', 'from' => null, 'to' => 'registrado', 'description' => 'Retiro registrado y marcado para revisión.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-19 11:45:00'],
                    ['action' => 'observa_retiro', 'from' => 'registrado', 'to' => 'observado', 'description' => 'Se mantiene observado hasta confirmar autorización.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-19 11:46:00'],
                ],
            ],
            [
                'student_rut' => '15555555-1',
                'status' => 'rechazado',
                'withdrawn_at' => '2026-06-21 12:15:00',
                'person_name' => 'Mauricio Torres',
                'person_relationship' => 'transporte',
                'person_phone' => '+56923330010',
                'reason' => 'tramite',
                'observations' => 'Solicitud no validada por inspectoría.',
                'person_authorized' => false,
                'authorization_source' => 'solicitud_externa',
                'requires_special_authorization' => true,
                'authorization_notes' => 'No se logró contactar apoderado.',
                'registered_by' => 'laura.diaz@cnscgestion.local',
                'authorized_by' => 'sergio.torres@cnscgestion.local',
                'authorization_request' => [
                    'status' => 'rechazada',
                    'required_permission_slug' => 'autorizar_retiros_porteria',
                    'reason' => 'Retiro solicitado por tercero sin acreditación.',
                    'requested_by' => 'laura.diaz@cnscgestion.local',
                    'resolved_by' => 'sergio.torres@cnscgestion.local',
                    'requested_at' => '2026-06-21 12:16:00',
                    'resolved_at' => '2026-06-21 12:30:00',
                    'resolution_notes' => 'Se rechaza por falta de autorización válida.',
                ],
                'logs' => [
                    ['action' => 'registro_retiro', 'from' => null, 'to' => 'registrado', 'description' => 'Retiro ingresado por tercero externo.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-21 12:15:00'],
                    ['action' => 'rechaza_retiro', 'from' => 'registrado', 'to' => 'rechazado', 'description' => 'Inspectoría rechaza la salida del estudiante.', 'performed_by' => 'sergio.torres@cnscgestion.local', 'performed_at' => '2026-06-21 12:30:00'],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $student = $this->student($definition['student_rut']);
            $enrollment = $this->activeEnrollment($student);

            if (!$enrollment) {
                continue;
            }

            $withdrawal = PorterStudentWithdrawal::query()->updateOrCreate(
                [
                    'student_profile_id' => $student->id,
                    'withdrawn_at' => $definition['withdrawn_at'],
                ],
                [
                    'academic_year_id' => $enrollment->academic_year_id,
                    'course_section_id' => $enrollment->course_section_id,
                    'registered_by' => $this->user($definition['registered_by'])->id,
                    'authorized_by' => isset($definition['authorized_by']) ? $this->user($definition['authorized_by'])->id : null,
                    'status' => $definition['status'],
                    'student_full_name_snapshot' => $student->full_name,
                    'student_rut_snapshot' => $student->rut,
                    'academic_year_name_snapshot' => $enrollment->snapshot_year_name,
                    'course_name_snapshot' => $enrollment->snapshot_course_display_name,
                    'person_name' => $definition['person_name'],
                    'person_rut' => $definition['person_rut'] ?? null,
                    'person_relationship' => $definition['person_relationship'],
                    'person_phone' => $definition['person_phone'] ?? null,
                    'reason' => $definition['reason'],
                    'observations' => $definition['observations'] ?? null,
                    'person_authorized' => $definition['person_authorized'],
                    'authorization_source' => $definition['authorization_source'] ?? null,
                    'requires_special_authorization' => $definition['requires_special_authorization'],
                    'authorization_notes' => $definition['authorization_notes'] ?? null,
                    'metadata' => ['seeded' => true],
                ],
            );

            if (!empty($definition['authorization_request'])) {
                $authorization = $definition['authorization_request'];

                $withdrawal->authorizationRequests()->updateOrCreate(
                    [
                        'required_permission_slug' => $authorization['required_permission_slug'],
                        'reason' => $authorization['reason'],
                    ],
                    [
                        'requested_by' => $this->user($authorization['requested_by'])->id,
                        'resolved_by' => isset($authorization['resolved_by']) ? $this->user($authorization['resolved_by'])->id : null,
                        'status' => $authorization['status'],
                        'requested_at' => $authorization['requested_at'],
                        'resolved_at' => $authorization['resolved_at'] ?? null,
                        'resolution_notes' => $authorization['resolution_notes'] ?? null,
                        'payload' => $authorization['payload'] ?? ['seeded' => true],
                    ],
                );
            }

            foreach ($definition['logs'] ?? [] as $log) {
                $withdrawal->logs()->updateOrCreate(
                    [
                        'action' => $log['action'],
                        'performed_at' => $log['performed_at'],
                    ],
                    [
                        'performed_by' => $this->user($log['performed_by'])->id,
                        'from_status' => $log['from'],
                        'to_status' => $log['to'],
                        'description' => $log['description'],
                        'payload' => ['seeded' => true],
                    ],
                );
            }
        }
    }

    private function seedReceivedItems(): void
    {
        $definitions = [
            [
                'recipient_type' => 'student',
                'student_rut' => '15555555-1',
                'status' => 'entregado_al_destinatario',
                'received_at' => '2026-06-17 09:20:00',
                'delivered_at' => '2026-06-17 13:15:00',
                'received_from_name' => 'Centro odontológico Valdivia',
                'item_type' => 'documento',
                'description' => 'Certificado de atención médica para apoderada.',
                'registered_by' => 'laura.diaz@cnscgestion.local',
                'delivered_by' => 'laura.diaz@cnscgestion.local',
                'delivered_to_name' => 'Ana Soto',
                'logs' => [
                    ['action' => 'recepcion_objeto', 'from' => null, 'to' => 'recibido_en_porteria', 'description' => 'Documento recepcionado en portería.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-17 09:20:00'],
                    ['action' => 'entrega_objeto', 'from' => 'recibido_en_porteria', 'to' => 'entregado_al_destinatario', 'description' => 'Documento entregado a apoderada.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-17 13:15:00'],
                ],
            ],
            [
                'recipient_type' => 'staff',
                'staff' => 'ivonne.reyes@cnscgestion.local',
                'recipient_label' => 'Ivonne Reyes Gallardo',
                'status' => 'pendiente',
                'received_at' => '2026-06-26 08:30:00',
                'received_from_name' => 'Farmacia Cruz Verde',
                'item_type' => 'medicamento',
                'description' => 'Insumos para enfermería pendientes de recepción formal.',
                'registered_by' => 'laura.diaz@cnscgestion.local',
                'logs' => [
                    ['action' => 'recepcion_objeto', 'from' => null, 'to' => 'pendiente', 'description' => 'Medicamento pendiente de retiro por enfermería.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-26 08:30:00'],
                ],
            ],
            [
                'recipient_type' => 'department',
                'department' => 'direccion',
                'recipient_label' => 'Dirección',
                'status' => 'derivado',
                'received_at' => '2026-06-26 10:45:00',
                'received_from_name' => 'CorreosChile',
                'item_type' => 'encomienda',
                'description' => 'Sobre institucional derivado a Dirección.',
                'registered_by' => 'laura.diaz@cnscgestion.local',
                'logs' => [
                    ['action' => 'recepcion_objeto', 'from' => null, 'to' => 'recibido_en_porteria', 'description' => 'Encomienda recepcionada.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-26 10:45:00'],
                    ['action' => 'derivacion_objeto', 'from' => 'recibido_en_porteria', 'to' => 'derivado', 'description' => 'Derivado a departamento de dirección.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-26 10:55:00'],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $student = isset($definition['student_rut']) ? $this->student($definition['student_rut']) : null;
            $enrollment = $student ? $this->activeEnrollment($student) : null;
            $staff = isset($definition['staff']) ? $this->staffByEmail($definition['staff']) : null;

            $item = PorterReceivedItem::query()->updateOrCreate(
                [
                    'recipient_type' => $definition['recipient_type'],
                    'description' => $definition['description'],
                    'received_at' => $definition['received_at'],
                ],
                [
                    'recipient_label' => $definition['recipient_label'] ?? $student?->full_name,
                    'student_profile_id' => $student?->id,
                    'staff_id' => $staff?->id,
                    'department_id' => isset($definition['department']) ? $this->department($definition['department'])->id : null,
                    'academic_year_id' => $enrollment?->academic_year_id,
                    'course_section_id' => $enrollment?->course_section_id,
                    'registered_by' => $this->user($definition['registered_by'])->id,
                    'delivered_by' => isset($definition['delivered_by']) ? $this->user($definition['delivered_by'])->id : null,
                    'status' => $definition['status'],
                    'delivered_at' => $definition['delivered_at'] ?? null,
                    'received_from_name' => $definition['received_from_name'],
                    'received_from_rut' => $definition['received_from_rut'] ?? null,
                    'received_from_phone' => $definition['received_from_phone'] ?? null,
                    'item_type' => $definition['item_type'],
                    'observations' => $definition['observations'] ?? null,
                    'delivered_to_name' => $definition['delivered_to_name'] ?? null,
                    'delivered_to_rut' => $definition['delivered_to_rut'] ?? null,
                    'delivery_observations' => $definition['delivery_observations'] ?? null,
                    'metadata' => ['seeded' => true],
                ],
            );

            foreach ($definition['logs'] ?? [] as $log) {
                $item->logs()->updateOrCreate(
                    [
                        'action' => $log['action'],
                        'performed_at' => $log['performed_at'],
                    ],
                    [
                        'performed_by' => $this->user($log['performed_by'])->id,
                        'from_status' => $log['from'],
                        'to_status' => $log['to'],
                        'description' => $log['description'],
                        'payload' => ['seeded' => true],
                    ],
                );
            }
        }
    }

    private function seedGoodsMovements(): void
    {
        $definitions = [
            [
                'movement_type' => 'recepcion_mercaderia',
                'department' => 'recursos-humanos',
                'responsible_staff' => 'marcelo.rojas@cnscgestion.local',
                'registered_by' => 'laura.diaz@cnscgestion.local',
                'delivered_by' => 'laura.diaz@cnscgestion.local',
                'status' => 'entregado_a_responsable',
                'moved_at' => '2026-06-24 09:10:00',
                'delivered_at' => '2026-06-24 09:40:00',
                'contact_name' => 'Transportes Austral',
                'company' => 'Transportes Austral',
                'goods_detail' => 'Cajas con tóner y papelería para RRHH',
                'quantity' => 4,
                'unit' => 'cajas',
                'document_type' => 'guia_despacho',
                'document_number' => 'GD-2026-7781',
                'received_by_name' => 'Marcelo Rojas Fuenzalida',
                'logs' => [
                    ['action' => 'registro_mercaderia', 'from' => null, 'to' => 'recibido_en_porteria', 'description' => 'Mercadería recepcionada en portería.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-24 09:10:00'],
                    ['action' => 'entrega_mercaderia', 'from' => 'recibido_en_porteria', 'to' => 'entregado_a_responsable', 'description' => 'Mercadería entregada a RRHH.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-24 09:40:00'],
                ],
            ],
            [
                'movement_type' => 'recepcion_mercaderia',
                'department' => 'mantencion',
                'responsible_staff' => 'ricardo.fuentes@cnscgestion.local',
                'registered_by' => 'laura.diaz@cnscgestion.local',
                'status' => 'derivado_a_departamento',
                'moved_at' => '2026-06-26 15:30:00',
                'contact_name' => 'Ferretería del Sur',
                'company' => 'Ferretería del Sur',
                'goods_detail' => 'Sellos, planchas y fijaciones para cubierta de gimnasio',
                'quantity' => 6,
                'unit' => 'bultos',
                'document_type' => 'factura',
                'document_number' => 'F-2026-348',
                'logs' => [
                    ['action' => 'registro_mercaderia', 'from' => null, 'to' => 'recibido_en_porteria', 'description' => 'Materiales de mantención recepcionados.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-26 15:30:00'],
                    ['action' => 'derivacion_mercaderia', 'from' => 'recibido_en_porteria', 'to' => 'derivado_a_departamento', 'description' => 'Se informa a mantención para retiro.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-26 15:45:00'],
                ],
            ],
            [
                'movement_type' => 'retiro_mercaderia',
                'department' => 'prevencion-de-riesgos',
                'responsible_staff' => 'nicolas.perez@cnscgestion.local',
                'registered_by' => 'laura.diaz@cnscgestion.local',
                'status' => 'rechazado',
                'moved_at' => '2026-06-27 12:00:00',
                'contact_name' => 'Proveedor externo no registrado',
                'company' => 'Sin razón social validada',
                'goods_detail' => 'Escalera telescópica sin orden de compra',
                'quantity' => 1,
                'unit' => 'unidad',
                'document_type' => 'otro',
                'document_number' => 'S/N',
                'observations' => 'Se rechaza ingreso por falta de respaldo documental.',
                'logs' => [
                    ['action' => 'registro_mercaderia', 'from' => null, 'to' => 'pendiente', 'description' => 'Ingreso marcado para revisión.', 'performed_by' => 'laura.diaz@cnscgestion.local', 'performed_at' => '2026-06-27 12:00:00'],
                    ['action' => 'rechazo_mercaderia', 'from' => 'pendiente', 'to' => 'rechazado', 'description' => 'Mercadería rechazada por prevención de riesgos.', 'performed_by' => 'nicolas.perez@cnscgestion.local', 'performed_at' => '2026-06-27 12:25:00'],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $movement = PorterGoodsMovement::query()->updateOrCreate(
                [
                    'movement_type' => $definition['movement_type'],
                    'moved_at' => $definition['moved_at'],
                    'goods_detail' => $definition['goods_detail'],
                ],
                [
                    'department_id' => isset($definition['department']) ? $this->department($definition['department'])->id : null,
                    'responsible_staff_id' => isset($definition['responsible_staff']) ? $this->staffByEmail($definition['responsible_staff'])->id : null,
                    'registered_by' => $this->user($definition['registered_by'])->id,
                    'delivered_by' => isset($definition['delivered_by']) ? $this->user($definition['delivered_by'])->id : null,
                    'status' => $definition['status'],
                    'delivered_at' => $definition['delivered_at'] ?? null,
                    'contact_name' => $definition['contact_name'],
                    'contact_rut' => $definition['contact_rut'] ?? null,
                    'company' => $definition['company'] ?? null,
                    'phone' => $definition['phone'] ?? null,
                    'vehicle_plate' => $definition['vehicle_plate'] ?? null,
                    'quantity' => $definition['quantity'] ?? null,
                    'unit' => $definition['unit'] ?? null,
                    'document_type' => $definition['document_type'] ?? null,
                    'document_number' => $definition['document_number'] ?? null,
                    'observations' => $definition['observations'] ?? null,
                    'received_by_name' => $definition['received_by_name'] ?? null,
                    'received_by_identifier' => $definition['received_by_identifier'] ?? null,
                    'delivery_observations' => $definition['delivery_observations'] ?? null,
                    'metadata' => ['seeded' => true],
                ],
            );

            foreach ($definition['logs'] ?? [] as $log) {
                $movement->logs()->updateOrCreate(
                    [
                        'action' => $log['action'],
                        'performed_at' => $log['performed_at'],
                    ],
                    [
                        'performed_by' => $this->user($log['performed_by'])->id,
                        'from_status' => $log['from'],
                        'to_status' => $log['to'],
                        'description' => $log['description'],
                        'payload' => ['seeded' => true],
                    ],
                );
            }
        }
    }
}
