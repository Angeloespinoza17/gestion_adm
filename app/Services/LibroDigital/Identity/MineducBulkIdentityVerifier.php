<?php

namespace App\Services\LibroDigital\Identity;

use App\Contracts\LibroDigital\BulkTeacherIdentityVerifier;
use App\DTO\LibroDigital\IdentityVerificationResult;
use App\Services\LibroDigital\CanonicalJson;
use App\ValueObjects\LibroDigital\ChileanRun;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class MineducBulkIdentityVerifier implements BulkTeacherIdentityVerifier
{
    public function __construct(
        private readonly CanonicalJson $canonical,
        private readonly IdentityCircuitBreaker $circuit,
    ) {}

    public function verifyMany(array $items): array
    {
        $url = (string) config('libro_digital.identity_verifier.bulk_url');
        if ($url === '' || $items === [] || $this->circuit->isOpen()) {
            return $this->unavailable($items);
        }

        // Contrato público masivo: las denominaciones son exactamente RUT,
        // OTP y TIMESTAMP. Los códigos solo existen en memoria durante el POST.
        $payload = collect($items)->map(fn ($item) => [
            'RUT' => $item->run,
            'OTP' => $item->otp,
            'TIMESTAMP' => $item->timestamp,
        ])->values()->all();

        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('libro_digital.identity_verifier.connect_timeout_seconds', 3))
                ->timeout((int) config('libro_digital.identity_verifier.timeout_seconds', 10))
                ->withHeader('X-Correlation-ID', $items[0]->correlationId)
                ->post($url, $payload);
        } catch (ConnectionException|Throwable) {
            $this->circuit->failure();

            return $this->unavailable($items);
        }

        if ($response->serverError()) {
            $this->circuit->failure();

            return $this->unavailable($items);
        }
        $this->circuit->success();
        if (! $response->successful() || ! is_array($response->json())) {
            return collect($items)->mapWithKeys(fn ($item) => [ChileanRun::normalize($item->run) => IdentityVerificationResult::rejected('HTTP_'.$response->status())])->all();
        }

        $rows = array_is_list($response->json()) ? $response->json() : ($response->json('items') ?? []);
        $byRun = collect($rows)->filter('is_array')->keyBy(fn (array $row) => ChileanRun::normalize((string) ($row['RUT'] ?? $row['rut'] ?? '')));

        return collect($items)->mapWithKeys(function ($item) use ($byRun): array {
            $run = ChileanRun::normalize($item->run);
            $row = $byRun->get($run);
            if (! is_array($row)) {
                return [$run => IdentityVerificationResult::rejected('MISSING_RESULT')];
            }
            $verified = ($row['verified'] ?? $row['valid'] ?? $row['resultado'] ?? false) === true
                || in_array(mb_strtolower((string) ($row['status'] ?? '')), ['ok', 'valid', 'verified', 'success'], true);
            $safe = collect($row)->except(['RUT', 'rut', 'OTP', 'otp', 'token', 'password'])->all();

            return [$run => $verified
                ? new IdentityVerificationResult(true, 'verified', responseCode: 'OK', responseHash: $this->canonical->hash($safe), safeMessage: 'Identidad docente verificada.')
                : IdentityVerificationResult::rejected((string) ($row['code'] ?? 'REJECTED'))];
        })->all();
    }

    public function provider(): string
    {
        return 'mineduc_bulk';
    }

    private function unavailable(array $items): array
    {
        return collect($items)->mapWithKeys(fn ($item) => [ChileanRun::normalize($item->run) => IdentityVerificationResult::unavailable()])->all();
    }
}
