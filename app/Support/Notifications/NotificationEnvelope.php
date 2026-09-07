<?php

namespace App\Support\Notifications;

use App\Models\User;
use DateTimeInterface;

final class NotificationEnvelope
{
    /**
     * @param  array{type:string,id:int|string,code?:string|null}  $resource
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function make(
        string $eventKey,
        string $eventType,
        string $module,
        string $title,
        string $message,
        array $resource,
        ?string $actionUrl = null,
        string $icon = 'bx bx-bell',
        string $priority = 'media',
        DateTimeInterface|string|null $occurredAt = null,
        ?User $actor = null,
        array $context = [],
    ): array {
        $safeActionUrl = self::safeActionUrl($actionUrl);

        return [
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'priority' => self::priority($priority),
            'action_url' => $safeActionUrl,
            'module' => $module,
            'event_type' => $eventType,
            'event' => [
                'schema' => 'cnsc.operational-notification.v1',
                'key' => $eventKey,
                'type' => $eventType,
                'module' => $module,
                'resource' => array_filter([
                    'type' => $resource['type'],
                    'id' => $resource['id'],
                    'code' => $resource['code'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                'occurred_at' => self::dateTime($occurredAt),
                'actor' => $actor ? [
                    'id' => (int) $actor->id,
                    'name' => $actor->name,
                ] : null,
                'context' => $context,
            ],
        ];
    }

    public static function priority(?string $priority): string
    {
        return match (mb_strtolower(trim((string) $priority))) {
            'critical', 'critica', 'critico', 'urgente', 'emergencia' => 'critica',
            'high', 'alta', 'alto' => 'alta',
            'low', 'baja', 'bajo' => 'baja',
            default => 'media',
        };
    }

    public static function safeActionUrl(?string $url): ?string
    {
        if (! $url || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return $url;
    }

    private static function dateTime(DateTimeInterface|string|null $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return $value ?: now()->toAtomString();
    }
}
