<?php

namespace App\Services\Porter;

use App\Models\PorterStudentWithdrawal;
use App\Models\User;
use App\Notifications\StudentWithdrawalCreatedNotification;
use App\Notifications\StudentWithdrawalStatusChangedNotification;
use App\Services\Inspectoria\InspectoriaAccessService;
use App\Services\Notifications\OperationalNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class StudentWithdrawalNotificationService
{
    public function __construct(private readonly OperationalNotificationService $notifications) {}

    public function created(PorterStudentWithdrawal $withdrawal, ?User $actor = null): int
    {
        $recipients = $this->creationRecipients($withdrawal);

        if ($recipients->isEmpty()) {
            Log::warning('El retiro quedó sin destinatario de notificación interna.', [
                'withdrawal_id' => $withdrawal->id,
                'course_section_id' => $withdrawal->course_section_id,
                'inspector_staff_id' => $withdrawal->inspector_staff_id,
            ]);
        }

        return $this->notifications->send(
            $recipients,
            new StudentWithdrawalCreatedNotification($withdrawal, $actor),
            'porter.withdrawal.created:'.$withdrawal->id,
        );
    }

    public function statusChanged(PorterStudentWithdrawal $withdrawal, User $actor): int
    {
        $withdrawal->loadMissing(['inspector.user', 'registeredBy']);
        $recipients = collect([
            $withdrawal->inspector?->user,
            $withdrawal->registeredBy,
        ])->filter(fn (?User $user): bool => $user && (int) $user->id !== (int) $actor->id);

        return $this->notifications->send(
            $recipients,
            new StudentWithdrawalStatusChangedNotification($withdrawal, $actor),
            sprintf('porter.withdrawal.status:%d:%s', $withdrawal->id, $withdrawal->status),
        );
    }

    /** @return Collection<int, User> */
    private function creationRecipients(PorterStudentWithdrawal $withdrawal): Collection
    {
        $withdrawal->loadMissing('inspector.user');
        $inspectorUser = $withdrawal->inspector?->user;
        $recipients = collect([$inspectorUser]);

        if (! $inspectorUser) {
            $recipients = $recipients->merge(
                $this->notifications->usersWithAnyPermission([InspectoriaAccessService::ASSIGNMENTS])
            );
        }

        if ($withdrawal->requires_special_authorization && $withdrawal->status === 'observado') {
            $recipients = $recipients->merge(
                $this->notifications->usersWithAnyPermission(['autorizar_retiros_porteria'])
            );
        }

        return $recipients
            ->filter(fn (?User $user): bool => $user && $user->active)
            ->unique('id')
            ->values();
    }
}
