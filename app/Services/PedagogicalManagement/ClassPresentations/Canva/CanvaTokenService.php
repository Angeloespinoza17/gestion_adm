<?php

namespace App\Services\PedagogicalManagement\ClassPresentations\Canva;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use App\Models\PedagogicalManagement\CanvaConnection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CanvaTokenService
{
    public function __construct(private readonly CanvaApiClient $client) {}

    public function accessToken(CanvaConnection $connection): string
    {
        if ($connection->status !== CanvaConnectionStatus::Active || ! $connection->access_token_encrypted) {
            throw new CanvaIntegrationException('La cuenta de Canva debe volver a conectarse.', 'CANVA_REAUTHORIZATION_REQUIRED', 409);
        }
        $leeway = max(60, (int) config('canva.token_refresh_leeway_seconds', 300));
        if ($connection->access_token_expires_at?->isAfter(now()->addSeconds($leeway))) {
            $this->touchUsage($connection);

            return (string) $connection->access_token_encrypted;
        }

        return DB::transaction(function () use ($connection, $leeway): string {
            $locked = CanvaConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CanvaConnectionStatus::Active || ! $locked->refresh_token_encrypted) {
                throw new CanvaIntegrationException('La cuenta de Canva debe volver a conectarse.', 'CANVA_REAUTHORIZATION_REQUIRED', 409);
            }
            if ($locked->access_token_expires_at?->isAfter(now()->addSeconds($leeway)) && $locked->access_token_encrypted) {
                $this->touchUsage($locked);

                return (string) $locked->access_token_encrypted;
            }

            try {
                $payload = $this->client->refreshAccessToken((string) $locked->refresh_token_encrypted);
            } catch (CanvaIntegrationException $exception) {
                if (! $exception->retryable) {
                    $locked->forceFill([
                        'status' => CanvaConnectionStatus::ReauthorizationRequired,
                        'access_token_encrypted' => null,
                        'refresh_token_encrypted' => null,
                    ])->save();
                }
                throw $exception;
            }
            $accessToken = trim((string) ($payload['access_token'] ?? ''));
            $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));
            if ($accessToken === '' || $refreshToken === '') {
                throw new CanvaIntegrationException('Canva no devolvió tokens renovados válidos.', 'CANVA_REFRESH_TOKEN_INVALID', 502);
            }
            $locked->forceFill([
                'access_token_encrypted' => $accessToken,
                'refresh_token_encrypted' => $refreshToken,
                'scopes' => $this->scopes($payload['scope'] ?? null),
                'access_token_expires_at' => now()->addSeconds(max(60, (int) ($payload['expires_in'] ?? 3600))),
                'last_refreshed_at' => now(),
                'last_used_at' => now(),
            ])->save();
            $connection->setRawAttributes($locked->getAttributes(), true)->syncOriginal();

            return $accessToken;
        }, 3);
    }

    public function disconnect(CanvaConnection $connection): void
    {
        $token = (string) ($connection->refresh_token_encrypted ?: $connection->access_token_encrypted);
        $remoteFailure = null;
        try {
            $this->client->revokeToken($token);
        } catch (Throwable $exception) {
            $remoteFailure = $exception;
        }
        $connection->forceFill([
            'status' => CanvaConnectionStatus::Revoked,
            'access_token_encrypted' => null,
            'refresh_token_encrypted' => null,
            'revoked_at' => now(),
        ])->save();
        if ($remoteFailure instanceof CanvaIntegrationException && $remoteFailure->retryable) {
            report($remoteFailure);
        }
    }

    /** @return list<string> */
    public function scopes(mixed $scope): array
    {
        if (is_array($scope)) {
            return array_values(array_filter($scope, 'is_string'));
        }

        return array_values(array_filter(preg_split('/\s+/', trim((string) $scope)) ?: []));
    }

    private function touchUsage(CanvaConnection $connection): void
    {
        if (! $connection->last_used_at || $connection->last_used_at->lt(now()->subMinutes(5))) {
            CanvaConnection::query()->whereKey($connection->id)->update(['last_used_at' => now(), 'updated_at' => now()]);
            $connection->setAttribute('last_used_at', now());
        }
    }
}
