<?php

namespace App\Services\PedagogicalManagement\ClassPresentations\Canva;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use App\Models\PedagogicalManagement\CanvaConnection;
use App\Models\User;

class CanvaConnectionLocator
{
    public function __construct(
        private readonly CanvaApiClient $client,
        private readonly CanvaAutofillAccessPolicy $autofillAccess,
    ) {}

    public function current(User $user, int $schoolId): ?CanvaConnection
    {
        return CanvaConnection::query()
            ->where('school_id', $schoolId)
            ->where('user_id', $user->id)
            ->whereIn('status', [
                CanvaConnectionStatus::Active->value,
                CanvaConnectionStatus::ReauthorizationRequired->value,
            ])
            ->latest('id')
            ->first();
    }

    public function active(User $user, int $schoolId): CanvaConnection
    {
        $connection = CanvaConnection::query()
            ->where('school_id', $schoolId)
            ->where('user_id', $user->id)
            ->where('status', CanvaConnectionStatus::Active->value)
            ->latest('id')
            ->first();
        if (! $connection) {
            throw new CanvaIntegrationException('Conecta una cuenta de Canva para este establecimiento.', 'CANVA_CONNECTION_REQUIRED', 409);
        }

        return $connection;
    }

    /** @return array<string,mixed> */
    public function response(?CanvaConnection $connection, int $schoolId): array
    {
        $names = array_values(array_unique(array_filter((array) ($connection?->capabilities ?? []), 'is_string')));
        $active = $connection?->status === CanvaConnectionStatus::Active;
        $needsReauthorization = $connection?->status === CanvaConnectionStatus::ReauthorizationRequired;
        $autofill = $this->autofillAccess->state($connection);
        $capabilities = [
            'autofill' => $autofill['enterprise_autofill'],
            'brand_template' => $autofill['brand_template'],
        ];

        return [
            'configured' => $this->client->isConfigured(),
            'connected' => $active,
            'school_id' => $schoolId,
            'connection_id' => $connection?->uuid,
            'status' => $connection?->status?->value,
            'needs_reauthorization' => $needsReauthorization,
            'reconnect_required' => $needsReauthorization,
            'enterprise_autofill' => $autofill['enterprise_autofill'],
            'trial_enabled' => $autofill['trial_enabled'],
            'mode' => $autofill['mode'],
            'autofill_available' => $autofill['autofill_available'],
            'capabilities' => $capabilities,
            'capability_names' => $names,
            'scopes' => array_values(array_filter((array) ($connection?->scopes ?? []), 'is_string')),
            'canva_user_id' => $connection?->canva_user_id,
            'canva_team_id' => $connection?->canva_team_id,
            'display_name' => $connection?->display_name,
            'account' => $connection ? [
                'display_name' => $connection->display_name,
                'user_id' => $connection->canva_user_id,
                'team_id' => $connection->canva_team_id,
            ] : null,
            'expires_at' => $connection?->access_token_expires_at?->toIso8601String(),
        ];
    }
}
