<?php

namespace App\Notifications;

use App\Models\PorterStudentWithdrawal;
use App\Models\User;
use App\Support\Notifications\NotificationEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentWithdrawalCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PorterStudentWithdrawal $withdrawal,
        private readonly ?User $actor = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return NotificationEnvelope::make(
            eventKey: 'porter.withdrawal.created:'.$this->withdrawal->id,
            eventType: 'withdrawal.created',
            module: 'porter',
            title: $this->withdrawal->requires_special_authorization
                ? 'Retiro pendiente de autorización'
                : 'Nuevo retiro de estudiante',
            message: sprintf(
                '%s, del curso %s, fue retirada por %s.',
                $this->withdrawal->student_full_name_snapshot,
                $this->withdrawal->course_name_snapshot,
                $this->withdrawal->person_name,
            ),
            resource: [
                'type' => 'porter_student_withdrawal',
                'id' => $this->withdrawal->id,
                'code' => $this->withdrawal->withdrawal_code,
            ],
            actionUrl: $this->actionUrl($notifiable),
            icon: 'bx bx-log-out-circle',
            priority: $this->withdrawal->requires_special_authorization ? 'alta' : 'media',
            occurredAt: $this->withdrawal->withdrawn_at,
            actor: $this->actor,
            context: [
                'student_profile_id' => $this->withdrawal->student_profile_id,
                'course_section_id' => $this->withdrawal->course_section_id,
                'status' => $this->withdrawal->status,
                'requires_special_authorization' => (bool) $this->withdrawal->requires_special_authorization,
            ],
        ) + [
            // Compatibilidad con notificaciones históricas y consumidores
            // existentes; la representación canónica vive dentro de event.
            'withdrawal_id' => $this->withdrawal->id,
            'withdrawal_code' => $this->withdrawal->withdrawal_code,
            'student_profile_id' => $this->withdrawal->student_profile_id,
            'status' => $this->withdrawal->status,
        ];
    }

    private function actionUrl(object $notifiable): ?string
    {
        if ($notifiable instanceof User
            && (int) $notifiable->staff_id === (int) $this->withdrawal->inspector_staff_id) {
            return '/inspectoria/retiros';
        }

        if ($notifiable instanceof User && (
            $notifiable->hasPermission('ver_porteria')
            || $notifiable->hasPermission('ver_historial_porteria')
            || $notifiable->hasPermission('autorizar_retiros_porteria')
        )) {
            return '/porter/withdrawals';
        }

        return $notifiable instanceof User && $notifiable->hasPermission('ver_retiros_inspectoria')
            ? '/inspectoria/retiros'
            : null;
    }
}
