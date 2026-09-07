<?php

namespace App\Services\SocialWork;

use App\Models\SocialWork\Referral;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use App\Notifications\OperationalEventNotification;
use App\Services\Notifications\OperationalNotificationService;

class SocialWorkNotificationService
{
    public function __construct(private readonly OperationalNotificationService $notifications) {}

    public function referralCreated(Referral $referral, User $actor): int
    {
        if ($referral->status !== 'enviada') {
            return 0;
        }

        $eventKey = 'social_work.referral.created:'.$referral->id;
        $recipients = $this->notifications
            ->usersWithAnyPermission(['social_work.referrals.manage'])
            ->reject(fn (User $user): bool => (int) $user->id === (int) $actor->id);

        return $this->notifications->send(
            $recipients,
            new OperationalEventNotification(
                eventKey: $eventKey,
                eventType: 'social_work.referral.created',
                module: 'social_work',
                title: 'Nueva derivación a Trabajo Social',
                message: 'Hay una nueva derivación pendiente de revisión en Trabajo Social.',
                resource: ['type' => 'social_work_referral', 'id' => $referral->id],
                actionUrl: '/social-work/referrals',
                icon: 'bx bx-git-branch',
                priority: $referral->urgency,
                occurredAt: $referral->created_at,
                actor: $actor,
                context: ['status' => $referral->status, 'source_unit' => $referral->source_unit],
            ),
            $eventKey,
        );
    }

    public function referralConverted(Referral $referral, SocialCase $case, User $actor): int
    {
        $eventKey = 'social_work.referral.converted:'.$referral->id;
        $recipients = User::query()
            ->where('active', true)
            ->whereIn('id', array_values(array_unique(array_filter([
                $referral->created_by,
                $case->responsible_user_id,
            ]))))
            ->whereKeyNot($actor->id)
            ->get();

        return $this->notifications->send(
            $recipients,
            new OperationalEventNotification(
                eventKey: $eventKey,
                eventType: 'social_work.referral.converted',
                module: 'social_work',
                title: 'Derivación recibida por Trabajo Social',
                message: 'La derivación fue recibida y convertida en un caso con seguimiento.',
                resource: ['type' => 'social_work_referral', 'id' => $referral->id],
                actionUrl: '/social-work/referrals',
                icon: 'bx bx-check-shield',
                priority: $case->priority,
                occurredAt: $referral->received_at,
                actor: $actor,
                context: ['status' => $referral->status, 'case_id' => $case->id],
            ),
            $eventKey,
        );
    }
}
