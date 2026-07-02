<?php

namespace App\Services\Security;

use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityNotification;
use App\Models\User;
use App\Notifications\SecurityIncidentPriorityNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class SecurityIncidentAlertService
{
    public function dispatchIfNeeded(SecurityIncident $incident): void
    {
        if (!in_array($incident->priority, ['alta', 'critica'], true)) {
            return;
        }

        if ($incident->alert_sent_at) {
            return;
        }

        $recipients = $this->resolveRecipients($incident);
        if ($recipients->isEmpty()) {
            return;
        }

        $title = $incident->priority === 'critica'
            ? 'Alerta crítica de seguridad'
            : 'Alerta prioritaria de seguridad';

        $message = sprintf(
            '%s en %s: %s',
            ucfirst($incident->priority),
            $incident->sector_name ?: $incident->shift?->coverage_label ?: 'sector no identificado',
            $incident->title,
        );

        $createdNotifications = collect();

        foreach ($recipients as $recipient) {
            $createdNotifications->push(
                SecurityNotification::create([
                    'user_id' => $recipient->id,
                    'security_incident_id' => $incident->id,
                    'title' => $title,
                    'message' => $message,
                    'priority' => $incident->priority,
                    'action_url' => '/security/incidents',
                ])
            );
        }

        $mailableRecipients = $recipients->filter(fn (User $user) => !empty($user->email))->values();
        if ($mailableRecipients->isNotEmpty()) {
            Notification::send($mailableRecipients, new SecurityIncidentPriorityNotification($incident));
            $createdNotifications->each->update([
                'sent_via_mail_at' => now(),
            ]);
        }

        $incident->forceFill([
            'alert_sent_at' => now(),
        ])->save();
    }

    private function resolveRecipients(SecurityIncident $incident): Collection
    {
        $incident->loadMissing([
            'currentResponsible:id,name,email,active',
        ]);

        $currentAssignees = $incident->assignments()
            ->with('user:id,name,email,active')
            ->where('is_current', true)
            ->get()
            ->pluck('user');

        $adminUsers = User::query()
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super_admin', 'administrador']))
            ->get(['id', 'name', 'email', 'active']);

        return collect()
            ->merge($adminUsers)
            ->push($incident->currentResponsible)
            ->merge($currentAssignees)
            ->filter(fn (?User $user) => $user && $user->active)
            ->unique('id')
            ->values();
    }
}
