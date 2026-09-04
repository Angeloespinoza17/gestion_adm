<?php

namespace App\Services\Integrations;

use App\Exceptions\GeoVictoriaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoVictoriaClient
{
    /** @return array<int, array<string, mixed>> */
    public function users(): array
    {
        $payload = $this->authenticatedPost(
            (string) config('services.geovictoria.users_path'),
            [],
        );

        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new GeoVictoriaException('GeoVictoria devolvió un catálogo de colaboradores no válido.');
        }

        return array_values(array_filter($payload, 'is_array'));
    }

    /**
     * @param  array<int, string>  $userIds
     * @return array<string, mixed>
     */
    public function attendanceBook(string $startDate, string $endDate, array $userIds): array
    {
        $payload = $this->authenticatedPost(
            (string) config('services.geovictoria.attendance_book_path'),
            [
                'StartDate' => $startDate,
                'EndDate' => $endDate,
                'UserIds' => implode(',', $userIds),
            ],
        );

        if (! is_array($payload) || ! is_array($payload['Users'] ?? null)) {
            throw new GeoVictoriaException('GeoVictoria devolvió un libro de asistencia no válido.');
        }

        return $payload;
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /** @return array<mixed> */
    private function authenticatedPost(string $path, array $payload): array
    {
        $response = $this->postWithToken($path, $payload, $this->token());

        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $this->postWithToken($path, $payload, $this->token());
        }

        $this->assertSuccessful($response);
        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new GeoVictoriaException('GeoVictoria devolvió una respuesta que no se pudo interpretar.');
        }

        return $decoded;
    }

    private function token(): string
    {
        $this->assertConfigured();

        return Cache::remember(
            $this->tokenCacheKey(),
            now()->addMinutes(max(1, (int) config('services.geovictoria.token_ttl_minutes', 270))),
            fn (): string => $this->requestToken(),
        );
    }

    private function requestToken(): string
    {
        try {
            $response = $this->request()->post(
                (string) config('services.geovictoria.login_path'),
                [
                    'User' => (string) config('services.geovictoria.api_key'),
                    'Password' => (string) config('services.geovictoria.api_secret'),
                ],
            );
        } catch (ConnectionException) {
            throw new GeoVictoriaException('No fue posible conectar con GeoVictoria. Reintenta en unos minutos.', 503);
        }

        $this->assertSuccessful($response, authenticating: true);
        $token = $response->json('token');

        if (! is_string($token) || trim($token) === '') {
            throw new GeoVictoriaException('GeoVictoria no entregó un token de acceso válido.');
        }

        return trim($token);
    }

    /** @param array<mixed> $payload */
    private function postWithToken(string $path, array $payload, string $token): Response
    {
        try {
            return $this->request()->withToken($token)->post($path, $payload);
        } catch (ConnectionException) {
            throw new GeoVictoriaException('No fue posible conectar con GeoVictoria. Reintenta en unos minutos.', 503);
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.geovictoria.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(max(1, (int) config('services.geovictoria.connect_timeout', 5)))
            ->timeout(max(5, (int) config('services.geovictoria.timeout', 45)));
    }

    private function assertConfigured(): void
    {
        $apiKey = trim((string) config('services.geovictoria.api_key'));
        $secret = trim((string) config('services.geovictoria.api_secret'));
        $baseUrl = trim((string) config('services.geovictoria.base_url'));

        if ($apiKey === '' || $secret === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new GeoVictoriaException('La integración con GeoVictoria aún no está configurada.', 503);
        }
    }

    private function assertSuccessful(Response $response, bool $authenticating = false): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $message = match (true) {
            $status === 401 || $status === 403 => $authenticating
                ? 'GeoVictoria rechazó las credenciales configuradas.'
                : 'GeoVictoria rechazó la autorización de la consulta.',
            $status === 404 => 'El servicio solicitado ya no está disponible en GeoVictoria.',
            $status === 408 || $status === 504 => 'GeoVictoria demoró demasiado en responder.',
            $status === 422 => 'GeoVictoria rechazó los parámetros de la consulta.',
            $status === 429 => 'GeoVictoria limitó temporalmente las consultas. Reintenta en unos minutos.',
            $status >= 500 => 'GeoVictoria no está disponible temporalmente.',
            default => 'GeoVictoria no pudo completar la consulta solicitada.',
        };

        throw new GeoVictoriaException($message, $status >= 500 ? 503 : 502, $status);
    }

    private function tokenCacheKey(): string
    {
        return 'geovictoria.jwt.'.hash('sha256', implode('|', [
            (string) config('services.geovictoria.base_url'),
            (string) config('services.geovictoria.api_key'),
        ]));
    }
}
