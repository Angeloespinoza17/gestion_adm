<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Throwable;

class OperationalNotificationService
{
    /**
     * Persists one database notification per event and recipient. The UUID is
     * deterministic, so controller retries cannot duplicate an operational event.
     *
     * @param  iterable<int, User|null>|User|null  $recipients
     */
    public function send(iterable|User|null $recipients, Notification $notification, string $eventKey): int
    {
        $users = $this->normalizeRecipients($recipients);

        if ($users->isEmpty()) {
            return 0;
        }

        try {
            $now = now();
            $rows = $users->map(function (User $user) use ($notification, $eventKey, $now): array {
                $payload = method_exists($notification, 'toDatabase')
                    ? $notification->toDatabase($user)
                    : $notification->toArray($user);

                return [
                    'id' => Uuid::uuid5(
                        Uuid::NAMESPACE_URL,
                        sprintf('cnsc:notification:%s:%s:%d', $notification::class, $eventKey, $user->id),
                    )->toString(),
                    'type' => method_exists($notification, 'databaseType')
                        ? $notification->databaseType($user)
                        : $notification::class,
                    'notifiable_type' => $user->getMorphClass(),
                    'notifiable_id' => $user->id,
                    'data' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            return DB::table('notifications')->insertOrIgnore($rows);
        } catch (Throwable $exception) {
            Log::warning('No se pudo persistir una notificación operacional.', [
                'event_key' => $eventKey,
                'notification' => $notification::class,
                'recipient_user_ids' => $users->pluck('id')->all(),
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     * @return Collection<int, User>
     */
    public function usersWithAnyPermission(array $permissionSlugs): Collection
    {
        $permissionSlugs = array_values(array_unique(array_filter($permissionSlugs)));

        if ($permissionSlugs === []) {
            return collect();
        }

        return User::query()
            ->where('active', true)
            ->where(function (Builder $query) use ($permissionSlugs): void {
                $query
                    ->whereHas('roles', fn (Builder $roles) => $roles->where('slug', 'super_admin'))
                    ->orWhereHas('roles.permissions', fn (Builder $permissions) => $permissions
                        ->where('permissions.active', true)
                        ->whereIn('permissions.slug', $permissionSlugs));
            })
            ->get(['users.id', 'users.name', 'users.email', 'users.staff_id', 'users.active']);
    }

    /**
     * @param  iterable<int, User|null>|User|null  $recipients
     * @return Collection<int, User>
     */
    private function normalizeRecipients(iterable|User|null $recipients): Collection
    {
        $items = $recipients instanceof User
            ? collect([$recipients])
            : collect($recipients);

        return $items
            ->filter(fn (mixed $recipient): bool => $recipient instanceof User && (bool) $recipient->active)
            ->unique(fn (User $recipient): int => (int) $recipient->id)
            ->values();
    }
}
