<?php

namespace App\Notifications;

use App\Models\PorterStudentWithdrawal;
use App\Models\User;
use App\Support\Notifications\NotificationEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentWithdrawalStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PorterStudentWithdrawal $withdrawal,
        private readonly User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusLabel = collect(PorterStudentWithdrawal::STATUS_OPTIONS)
            ->firstWhere('value', $this->withdrawal->status)['label'] ?? ucfirst($this->withdrawal->status);

        return NotificationEnvelope::make(
            eventKey: sprintf('porter.withdrawal.status:%d:%s', $this->withdrawal->id, $this->withdrawal->status),
            eventType: 'withdrawal.status_changed',
            module: 'porter',
            title: 'Retiro '.$statusLabel,
            message: sprintf(
                'El retiro %s de %s cambió a %s.',
                $this->withdrawal->withdrawal_code,
                $this->withdrawal->student_full_name_snapshot,
                mb_strtolower($statusLabel),
            ),
            resource: [
                'type' => 'porter_student_withdrawal',
                'id' => $this->withdrawal->id,
                'code' => $this->withdrawal->withdrawal_code,
            ],
            actionUrl: $this->actionUrl($notifiable),
            icon: $this->withdrawal->status === 'anulado' ? 'bx bx-x-circle' : 'bx bx-check-shield',
            priority: in_array($this->withdrawal->status, ['rechazado', 'anulado'], true) ? 'alta' : 'media',
            occurredAt: $this->withdrawal->cancelled_at ?? $this->withdrawal->updated_at,
            actor: $this->actor,
            context: [
                'student_profile_id' => $this->withdrawal->student_profile_id,
                'course_section_id' => $this->withdrawal->course_section_id,
                'status' => $this->withdrawal->status,
            ],
        );
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
