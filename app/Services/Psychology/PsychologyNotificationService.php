<?php

namespace App\Services\Psychology;

use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyReferral;
use App\Models\User;
use App\Notifications\Psychology\PsychologySafeNotification;

class PsychologyNotificationService
{
    public function referralSubmitted(PsychologyReferral $referral): void
    {
        User::query()->where('active', true)->whereHas('roles.permissions', fn ($q) => $q->whereIn('slug', ['psychology.referrals.view_all', 'psychology.referrals.assign']))
            ->get()->each->notify(new PsychologySafeNotification('Nueva derivación', 'Tienes una derivación de Psicología pendiente de revisión.', '/psychology/referrals'));
    }

    public function assigned(PsychologyReferral|PsychologyCase $subject, User $user): void
    {
        $user->notify(new PsychologySafeNotification('Asignación de Psicología', 'Tienes un registro de Psicología asignado para revisión.', '/psychology'));
    }

    public function criticalRisk(PsychologyCase $case): void
    {
        User::query()->where('active', true)->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'psychology.risk.view'))
            ->get()->each->notify(new PsychologySafeNotification('Alerta prioritaria', 'Existe una alerta prioritaria de Psicología que requiere revisión autorizada.', '/psychology/alerts'));
    }
}
