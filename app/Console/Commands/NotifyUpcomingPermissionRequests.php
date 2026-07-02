<?php

namespace App\Console\Commands;

use App\Models\PermissionRequest;
use App\Notifications\PermissionRequestStatusNotification;
use Illuminate\Console\Command;

class NotifyUpcomingPermissionRequests extends Command
{
    protected $signature = 'permissions:notify-upcoming {--days=2 : Days ahead to notify}';

    protected $description = 'Notify upcoming approved permission requests.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $from = now()->startOfDay();
        $to = now()->copy()->addDays($days)->endOfDay();

        $requests = PermissionRequest::query()
            ->with(['staff:id,full_name', 'permissionType:id,name', 'requestedBy:id,name,email', 'directManagerUser:id,name,email'])
            ->where('status', 'aprobado')
            ->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        foreach ($requests as $permissionRequest) {
            $headline = 'Tu permiso aprobado se ejecutará próximamente.';
            $subject = 'Permiso próximo a ejecutarse';

            if ($permissionRequest->requestedBy) {
                $permissionRequest->requestedBy->notify(
                    new PermissionRequestStatusNotification($permissionRequest, $subject, $headline)
                );
            }

            if ($permissionRequest->directManagerUser && $permissionRequest->directManagerUser->id !== $permissionRequest->requested_by_user_id) {
                $permissionRequest->directManagerUser->notify(
                    new PermissionRequestStatusNotification(
                        $permissionRequest,
                        $subject,
                        'Un permiso aprobado de tu equipo se ejecutará próximamente.'
                    )
                );
            }
        }

        $this->info('Notificaciones de permisos próximos enviadas: ' . $requests->count());

        return self::SUCCESS;
    }
}
