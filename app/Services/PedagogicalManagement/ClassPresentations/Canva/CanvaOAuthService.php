<?php

namespace App\Services\PedagogicalManagement\ClassPresentations\Canva;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\CanvaConnection;
use App\Models\PedagogicalManagement\CanvaOAuthState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CanvaOAuthService
{
    public function __construct(
        private readonly CanvaApiClient $client,
        private readonly CanvaTokenService $tokens,
    ) {}

    /** @return array{authorization_url:string,expires_at:string} */
    public function begin(User $user, School $school, ?string $redirectTo = null): array
    {
        if (! $this->client->isConfigured()) {
            throw new CanvaIntegrationException('La integración Canva no está configurada.', 'CANVA_NOT_CONFIGURED', 503);
        }
        $state = $this->randomUrlSafe(64);
        $verifier = $this->randomUrlSafe(64);
        $challenge = $this->base64Url(hash('sha256', $verifier, true));
        $expiresAt = now()->addMinutes(max(5, (int) config('canva.state_ttl_minutes', 10)));

        CanvaOAuthState::query()
            ->where('expires_at', '<', now()->subDay())
            ->delete();

        CanvaOAuthState::query()->create([
            'state_hash' => hash('sha256', $state),
            'school_id' => $school->id,
            'user_id' => $user->id,
            'code_verifier_encrypted' => $verifier,
            'redirect_to' => $this->safeRedirect($redirectTo),
            'expires_at' => $expiresAt,
        ]);

        $query = http_build_query([
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'scope' => implode(' ', (array) config('canva.scopes', [])),
            'response_type' => 'code',
            'client_id' => (string) config('canva.client_id'),
            'state' => $state,
            'redirect_uri' => (string) config('canva.redirect_uri'),
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'authorization_url' => rtrim((string) config('canva.authorize_url'), '?').'?'.$query,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /** @return array{connection:CanvaConnection,redirect_to:string} */
    public function complete(string $state, string $code): array
    {
        if (trim($state) === '' || trim($code) === '') {
            throw new CanvaIntegrationException('Canva no devolvió una autorización válida.', 'CANVA_OAUTH_CALLBACK_INVALID', 422);
        }
        $oauthState = DB::transaction(function () use ($state): CanvaOAuthState {
            $record = CanvaOAuthState::query()->where('state_hash', hash('sha256', $state))->lockForUpdate()->first();
            if (! $record || $record->consumed_at || $record->expires_at?->isPast()) {
                throw new CanvaIntegrationException('La autorización de Canva venció o ya fue utilizada.', 'CANVA_OAUTH_STATE_INVALID', 422);
            }
            $record->forceFill(['consumed_at' => now()])->save();

            return $record;
        }, 3);

        $payload = $this->client->exchangeAuthorizationCode($code, (string) $oauthState->code_verifier_encrypted);
        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));
        if ($accessToken === '' || $refreshToken === '') {
            throw new CanvaIntegrationException('Canva no devolvió credenciales de usuario válidas.', 'CANVA_OAUTH_TOKEN_INVALID', 502);
        }
        $remoteUser = $this->client->currentUser($accessToken);
        $canvaUserId = trim((string) data_get($remoteUser, 'team_user.user_id'));
        $canvaTeamId = trim((string) data_get($remoteUser, 'team_user.team_id'));
        if ($canvaUserId === '' || $canvaTeamId === '') {
            throw new CanvaIntegrationException('Canva no informó el usuario y equipo autorizados.', 'CANVA_USER_INVALID', 502);
        }

        CanvaConnection::query()
            ->where('school_id', $oauthState->school_id)
            ->where('user_id', $oauthState->user_id)
            ->where('canva_team_id', '!=', $canvaTeamId)
            ->where('status', CanvaConnectionStatus::Active->value)
            ->get()
            ->each(fn (CanvaConnection $connection) => $this->tokens->disconnect($connection));

        $connection = DB::transaction(function () use ($oauthState, $payload, $accessToken, $refreshToken, $remoteUser, $canvaUserId, $canvaTeamId): CanvaConnection {
            CanvaConnection::query()
                ->where('school_id', $oauthState->school_id)
                ->where('user_id', $oauthState->user_id)
                ->where('canva_team_id', '!=', $canvaTeamId)
                ->where('status', CanvaConnectionStatus::Active->value)
                ->update([
                    'access_token_encrypted' => null,
                    'refresh_token_encrypted' => null,
                    'status' => CanvaConnectionStatus::Revoked->value,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            return CanvaConnection::query()->updateOrCreate([
                'school_id' => $oauthState->school_id,
                'user_id' => $oauthState->user_id,
                'canva_team_id' => $canvaTeamId,
            ], [
                'canva_user_id' => $canvaUserId,
                'display_name' => data_get($remoteUser, 'profile.display_name'),
                'access_token_encrypted' => $accessToken,
                'refresh_token_encrypted' => $refreshToken,
                'scopes' => $this->tokens->scopes($payload['scope'] ?? null),
                'capabilities' => array_values((array) data_get($remoteUser, 'capabilities', [])),
                'status' => CanvaConnectionStatus::Active,
                'access_token_expires_at' => now()->addSeconds(max(60, (int) ($payload['expires_in'] ?? 3600))),
                'last_refreshed_at' => now(),
                'last_used_at' => now(),
                'revoked_at' => null,
            ]);
        }, 3);

        return [
            'connection' => $connection,
            'redirect_to' => $oauthState->redirect_to ?: '/gestion-pedagogica/generador-clases',
        ];
    }

    public function cancel(string $state): string
    {
        if (trim($state) === '') {
            return '/gestion-pedagogica/generador-clases';
        }

        return DB::transaction(function () use ($state): string {
            $record = CanvaOAuthState::query()
                ->where('state_hash', hash('sha256', $state))
                ->lockForUpdate()
                ->first();
            if (! $record || $record->consumed_at || $record->expires_at?->isPast()) {
                return '/gestion-pedagogica/generador-clases';
            }
            $record->forceFill(['consumed_at' => now()])->save();

            return $record->redirect_to ?: '/gestion-pedagogica/generador-clases';
        }, 3);
    }

    public function redirectForState(string $state): string
    {
        if (trim($state) === '') {
            return '/gestion-pedagogica/generador-clases';
        }

        return CanvaOAuthState::query()
            ->where('state_hash', hash('sha256', $state))
            ->value('redirect_to') ?: '/gestion-pedagogica/generador-clases';
    }

    private function randomUrlSafe(int $bytes): string
    {
        return $this->base64Url(random_bytes($bytes));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function safeRedirect(?string $redirectTo): string
    {
        $redirectTo = trim((string) $redirectTo);
        if ($redirectTo === ''
            || ! str_starts_with($redirectTo, '/')
            || str_starts_with($redirectTo, '//')
            || preg_match('/[\x00-\x1F\x7F]/', $redirectTo) === 1
        ) {
            return '/gestion-pedagogica/generador-clases';
        }

        return mb_substr($redirectTo, 0, 500);
    }
}
