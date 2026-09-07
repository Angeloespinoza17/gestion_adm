<?php

namespace App\Services\Psychology;

use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyReferral;
use App\Models\User;
use App\Notifications\OperationalEventNotification;
use App\Services\Notifications\OperationalNotificationService;

class PsychologyNotificationService
{
    public function __construct(private readonly OperationalNotificationService $notifications) {}

    public function referralSubmitted(PsychologyReferral $referral): void
    {
        $recipients = $this->notifications
            ->usersWithAnyPermission(['psychology.referrals.view_all', 'psychology.referrals.assign'])
            ->reject(fn (User $user): bool => in_array((int) $user->id, array_filter([
                (int) $referral->referred_by_user_id,
                (int) $referral->assigned_user_id,
            ]), true));

        $this->notifications->send(
            $recipients,
            $this->referralNotification(
                $referral,
                'psychology.referral.submitted',
                'Nueva derivación de Psicología',
                'Hay una nueva derivación pendiente de revisión profesional.',
                $referral->suggested_urgency,
            ),
            'psychology.referral.submitted:'.$referral->id,
        );
    }

    public function assigned(PsychologyReferral|PsychologyCase $subject, User $user): void
    {
        $type = $subject instanceof PsychologyReferral ? 'referral' : 'case';
        $eventKey = sprintf('psychology.%s.assigned:%d:%d', $type, $subject->id, $user->id);

        $this->notifications->send(
            $user,
            new OperationalEventNotification(
                eventKey: $eventKey,
                eventType: 'psychology.'.$type.'.assigned',
                module: 'psychology',
                title: 'Asignación de Psicología',
                message: 'Tienes un registro de Psicología asignado para revisión.',
                resource: [
                    'type' => 'psychology_'.$type,
                    'id' => $subject->id,
                    'code' => $subject->code,
                ],
                actionUrl: $subject instanceof PsychologyReferral ? '/psychology/referrals' : '/psychology/cases',
                icon: 'bx bx-brain',
                priority: $subject instanceof PsychologyReferral
                    ? ($subject->professional_priority ?: $subject->suggested_urgency)
                    : $subject->priority,
                occurredAt: $subject->updated_at,
                context: ['assigned_user_id' => $user->id],
            ),
            $eventKey,
        );
    }

    public function criticalRisk(PsychologyCase $case): void
    {
        $eventKey = 'psychology.case.critical-risk:'.$case->id;
        $this->notifications->send(
            $this->notifications->usersWithAnyPermission(['psychology.risk.view']),
            new OperationalEventNotification(
                eventKey: $eventKey,
                eventType: 'psychology.case.critical_risk',
                module: 'psychology',
                title: 'Alerta prioritaria',
                message: 'Existe una alerta prioritaria de Psicología que requiere revisión autorizada.',
                resource: ['type' => 'psychology_case', 'id' => $case->id, 'code' => $case->code],
                actionUrl: '/psychology/alerts',
                icon: 'bx bx-error-circle',
                priority: 'critica',
                occurredAt: $case->updated_at,
            ),
            $eventKey,
        );
    }

    public function referralStatusChanged(PsychologyReferral $referral, User $actor): void
    {
        $recipient = $referral->referredBy()->where('active', true)->first();
        if (! $recipient || (int) $recipient->id === (int) $actor->id) {
            return;
        }

        $eventKey = sprintf('psychology.referral.status:%d:%s', $referral->id, $referral->status);
        $this->notifications->send(
            $recipient,
            $this->referralNotification(
                $referral,
                'psychology.referral.status_changed',
                'Derivación de Psicología actualizada',
                'La derivación '.$referral->code.' cambió a '.$referral->status.'.',
                $referral->professional_priority ?: $referral->suggested_urgency,
                $actor,
                $eventKey,
            ),
            $eventKey,
        );
    }

    private function referralNotification(
        PsychologyReferral $referral,
        string $eventType,
        string $title,
        string $message,
        ?string $priority,
        ?User $actor = null,
        ?string $eventKey = null,
    ): OperationalEventNotification {
        $eventKey ??= $eventType.':'.$referral->id;

        return new OperationalEventNotification(
            eventKey: $eventKey,
            eventType: $eventType,
            module: 'psychology',
            title: $title,
            message: $message,
            resource: ['type' => 'psychology_referral', 'id' => $referral->id, 'code' => $referral->code],
            actionUrl: '/psychology/referrals',
            icon: 'bx bx-git-branch',
            priority: $priority ?: 'media',
            occurredAt: $referral->updated_at,
            actor: $actor,
            context: ['status' => $referral->status],
        );
    }
}
