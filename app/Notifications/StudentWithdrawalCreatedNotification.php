<?php

namespace App\Notifications;

use App\Models\PorterStudentWithdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentWithdrawalCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PorterStudentWithdrawal $withdrawal,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nuevo retiro de estudiante',
            'message' => sprintf(
                '%s, del curso %s, fue retirada por %s.',
                $this->withdrawal->student_full_name_snapshot,
                $this->withdrawal->course_name_snapshot,
                $this->withdrawal->person_name,
            ),
            'icon' => 'bx bx-log-out-circle',
            'priority' => $this->withdrawal->requires_special_authorization ? 'alta' : 'media',
            'action_url' => '/inspectoria/retiros',
            'withdrawal_id' => $this->withdrawal->id,
            'withdrawal_code' => $this->withdrawal->withdrawal_code,
            'student_profile_id' => $this->withdrawal->student_profile_id,
            'student_name' => $this->withdrawal->student_full_name_snapshot,
            'course_name' => $this->withdrawal->course_name_snapshot,
            'person_name' => $this->withdrawal->person_name,
            'status' => $this->withdrawal->status,
            'withdrawn_at' => $this->withdrawal->withdrawn_at?->format('Y-m-d H:i:s'),
        ];
    }
}
