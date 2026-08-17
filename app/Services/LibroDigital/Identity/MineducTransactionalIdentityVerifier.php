<?php

namespace App\Services\LibroDigital\Identity;

use App\Contracts\LibroDigital\TeacherIdentityVerifier;
use App\DTO\LibroDigital\IdentityVerificationData;
use App\DTO\LibroDigital\IdentityVerificationResult;
use App\Services\LibroDigital\CanonicalJson;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MineducTransactionalIdentityVerifier implements TeacherIdentityVerifier
{
    public function __construct(
        private readonly CanonicalJson $canonical,
        private readonly IdentityCircuitBreaker $circuit,
    ) {}

    public function verify(IdentityVerificationData $data): IdentityVerificationResult
    {
        $url = (string) config('libro_digital.identity_verifier.transactional_url');
        if ($url === '') {
            return IdentityVerificationResult::unavailable();
        }
        if ($this->circuit->isOpen()) {
            return IdentityVerificationResult::unavailable('El verificador de identidad está temporalmente protegido por el circuit breaker.');
        }

        try {
            // No se usa retry: el OTP podria consumirse dos veces.
            $response = Http::acceptJson()
                ->connectTimeout((int) config('libro_digital.identity_verifier.connect_timeout_seconds', 3))
                ->timeout((int) config('libro_digital.identity_verifier.timeout_seconds', 10))
                ->withHeaders([
                    'X-Correlation-ID' => $data->correlationId,
                    'Idempotency-Key' => $data->correlationId,
                ])
                ->get($url, [
                    // El contrato transaccional publico de EDE usa exactamente
                    // rut, otp y DateWithTimeZone (la variante masiva usa RUT).
                    'rut' => $data->run,
                    'otp' => $data->otp,
                    'DateWithTimeZone' => $data->timestamp,
                ]);
        } catch (ConnectionException|\Throwable) {
            // Nunca propagar la URL de la excepcion: puede incluir el OTP.
            $this->circuit->failure();

            return IdentityVerificationResult::unavailable();
        }

        if ($response->serverError()) {
            $this->circuit->failure();

            return IdentityVerificationResult::unavailable();
        } else {
            // Un OTP rechazado prueba disponibilidad y no abre el circuito.
            $this->circuit->success();
        }

        $decoded = $response->json();
        // La respuesta publica documentada puede ser un booleano JSON plano.
        $payload = is_array($decoded) ? $decoded : ['verified' => $decoded === true];
        $responseCode = $this->stringValue($payload, ['code', 'codigo', 'responseCode']);

        if (! $response->successful() || ! $this->isVerified($payload)) {
            return IdentityVerificationResult::rejected($responseCode ?: 'HTTP_'.$response->status());
        }

        return new IdentityVerificationResult(
            verified: true,
            status: 'verified',
            transactionId: $this->stringValue($payload, ['transactionId', 'transaction_id', 'idTransaccion']),
            responseCode: $responseCode ?: 'OK',
            responseHash: $this->canonical->hash($this->sanitizedPayload($payload)),
            safeMessage: 'Identidad docente verificada.',
        );
    }

    public function provider(): string
    {
        return 'mineduc_transactional';
    }

    private function isVerified(array $payload): bool
    {
        $value = $payload['verified'] ?? $payload['valid'] ?? $payload['resultado'] ?? $payload['status'] ?? null;

        return $value === true || in_array(mb_strtolower((string) $value), ['true', 'ok', 'valid', 'valido', 'verified', 'success'], true);
    }

    private function stringValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function sanitizedPayload(array $payload): array
    {
        return collect($payload)->except(['otp', 'OTP', 'run', 'RUN', 'rut', 'RUT', 'token', 'password'])->all();
    }
}
