<?php

namespace App\Services\PedagogicalManagement\ClassPresentations\Canva;

use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class CanvaApiClient
{
    public function isConfigured(): bool
    {
        return (bool) config('canva.enabled', false)
            && trim((string) config('canva.client_id')) !== ''
            && trim((string) config('canva.client_secret')) !== ''
            && trim((string) config('canva.redirect_uri')) !== '';
    }

    /** @return array<string,mixed> */
    public function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => (string) config('canva.redirect_uri'),
        ]);
    }

    /** @return array<string,mixed> */
    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function revokeToken(string $token): void
    {
        if (trim($token) === '') {
            return;
        }
        $this->assertConfigured();

        try {
            $response = $this->basicClient()->asForm()->post('/oauth/revoke', ['token' => $token]);
        } catch (Throwable) {
            throw new CanvaIntegrationException('No fue posible revocar la conexión en Canva.', 'CANVA_REVOKE_CONNECTION_FAILED', 502, true);
        }
        if (! $response->successful()) {
            $this->throwForResponse($response, 'CANVA_REVOKE_CONNECTION_FAILED');
        }
    }

    /** @return array{team_user:array<string,mixed>,profile:array<string,mixed>,capabilities:list<string>} */
    public function currentUser(string $accessToken): array
    {
        $this->assertConfigured();
        $teamUser = $this->get($accessToken, '/users/me');
        $profile = $this->get($accessToken, '/users/me/profile');
        $capabilities = $this->get($accessToken, '/users/me/capabilities');

        return [
            'team_user' => (array) ($teamUser['team_user'] ?? []),
            'profile' => (array) ($profile['profile'] ?? []),
            'capabilities' => array_values(array_filter((array) ($capabilities['capabilities'] ?? []), 'is_string')),
        ];
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function listBrandTemplates(string $accessToken, array $filters = []): array
    {
        $this->assertConfigured();
        $query = array_filter([
            'query' => $filters['query'] ?? null,
            'continuation' => $filters['continuation'] ?? null,
            'limit' => $filters['limit'] ?? 25,
            'ownership' => $filters['ownership'] ?? 'any',
            'sort_by' => $filters['sort_by'] ?? 'modified_descending',
            'dataset' => 'non_empty',
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $this->get($accessToken, '/brand-templates', $query);
    }

    /** @return array<string,mixed> */
    public function brandTemplateDataset(string $accessToken, string $brandTemplateId): array
    {
        $this->assertConfigured();

        return $this->get($accessToken, '/brand-templates/'.rawurlencode($brandTemplateId).'/dataset');
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function createAutofill(string $accessToken, string $brandTemplateId, array $data): array
    {
        $this->assertConfigured();

        return $this->post($accessToken, '/autofills', [
            'type' => 'create_from_brand_template',
            'brand_template_id' => $brandTemplateId,
            'data' => $data,
        ], false);
    }

    /** @return array<string,mixed> */
    public function autofillJob(string $accessToken, string $jobId): array
    {
        $this->assertConfigured();

        return $this->get($accessToken, '/autofills/'.rawurlencode($jobId));
    }

    /** @return array<string,mixed> */
    public function design(string $accessToken, string $designId): array
    {
        $this->assertConfigured();

        return $this->get($accessToken, '/designs/'.rawurlencode($designId));
    }

    /** @param array<string,string> $payload @return array<string,mixed> */
    private function tokenRequest(array $payload): array
    {
        $this->assertConfigured();
        try {
            $response = $this->basicClient()->asForm()->post('/oauth/token', $payload);
        } catch (Throwable) {
            throw new CanvaIntegrationException('Canva no respondió al autenticar la conexión.', 'CANVA_OAUTH_CONNECTION_FAILED', 502, true);
        }

        return $this->decode($response, 'CANVA_OAUTH_TOKEN_FAILED');
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    private function get(string $accessToken, string $path, array $query = []): array
    {
        try {
            $response = $this->bearerClient($accessToken)->get($path, $query);
        } catch (ConnectionException) {
            throw new CanvaIntegrationException('Canva no respondió dentro del tiempo disponible.', 'CANVA_TIMEOUT', 504, true);
        } catch (Throwable) {
            throw new CanvaIntegrationException('No fue posible conectar con Canva.', 'CANVA_CONNECTION_FAILED', 502, true);
        }

        return $this->decode($response, 'CANVA_HTTP_ERROR');
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function post(string $accessToken, string $path, array $payload, bool $retryAmbiguous): array
    {
        try {
            $response = $this->bearerClient($accessToken)->post($path, $payload);
        } catch (ConnectionException) {
            throw new CanvaIntegrationException(
                'Canva no confirmó la creación del diseño. Revisa el historial antes de volver a intentarlo.',
                'CANVA_SUBMISSION_UNCONFIRMED',
                504,
                $retryAmbiguous,
            );
        } catch (Throwable) {
            throw new CanvaIntegrationException('No fue posible enviar el contenido a Canva.', 'CANVA_CONNECTION_FAILED', 502, $retryAmbiguous);
        }

        return $this->decode($response, 'CANVA_HTTP_ERROR');
    }

    /** @return array<string,mixed> */
    private function decode(Response $response, string $fallbackCode): array
    {
        if (! $response->successful()) {
            $this->throwForResponse($response, $fallbackCode);
        }
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new CanvaIntegrationException('Canva devolvió una respuesta ilegible.', 'CANVA_RESPONSE_INVALID', 502, true);
        }

        return $payload;
    }

    private function throwForResponse(Response $response, string $fallbackCode): never
    {
        $remoteCode = strtoupper(preg_replace('/[^A-Z0-9_]+/i', '_', (string) $response->json('code')) ?: '');
        $trialQuotaExceeded = in_array($remoteCode, ['TRIAL_QUOTA_EXCEEDED', 'QUOTA_EXCEEDED'], true);
        $failureCode = $trialQuotaExceeded
            ? 'CANVA_TRIAL_QUOTA_EXCEEDED'
            : ($remoteCode !== '' ? 'CANVA_'.$remoteCode : $fallbackCode);
        $status = $response->status();
        $retryable = ! $trialQuotaExceeded && ($status === 429 || $status >= 500);
        $message = $trialQuotaExceeded
            ? 'La prueba de desarrollo de Autofill agotó su cuota. Se requiere Canva Enterprise para continuar.'
            : match ($status) {
                401 => 'La autorización de Canva venció o fue revocada. Vuelve a conectar tu cuenta.',
                403 => 'La cuenta de Canva no posee el permiso o plan requerido para esta operación.',
                404 => 'El recurso solicitado ya no existe o no está disponible en Canva.',
                429 => 'Canva alcanzó temporalmente su límite de solicitudes. La cola volverá a intentarlo.',
                default => 'Canva rechazó la operación solicitada.',
            };

        throw new CanvaIntegrationException($message, $failureCode, $status === 401 ? 409 : $status, $retryable);
    }

    private function basicClient(): PendingRequest
    {
        return $this->client()->withBasicAuth((string) config('canva.client_id'), (string) config('canva.client_secret'));
    }

    private function bearerClient(string $accessToken): PendingRequest
    {
        return $this->client()->withToken($accessToken);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('canva.base_url'))
            ->acceptJson()
            ->connectTimeout(max(2, (int) config('canva.connect_timeout_seconds', 8)))
            ->timeout(max(5, (int) config('canva.timeout_seconds', 30)));
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new CanvaIntegrationException('La integración Canva no está configurada.', 'CANVA_NOT_CONFIGURED', 503);
        }
    }
}
