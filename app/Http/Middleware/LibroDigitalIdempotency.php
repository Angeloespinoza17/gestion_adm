<?php

namespace App\Http\Middleware;

use App\Services\LibroDigital\CanonicalJson;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class LibroDigitalIdempotency
{
    public function __construct(private readonly CanonicalJson $canonical) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if (! preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key)) {
            return $this->error($request, 'La operacion requiere un Idempotency-Key valido.', 'LCD_IDEMPOTENCY_KEY_REQUIRED', 422);
        }

        $userId = (int) ($request->user()?->getKey() ?? 0);
        $cacheKey = 'lcd:idempotency:'.$userId.':'.hash('sha256', $key);
        $fingerprint = $this->fingerprint($request, $userId);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            if (! hash_equals((string) ($cached['fingerprint'] ?? ''), $fingerprint)) {
                return $this->error($request, 'El Idempotency-Key ya fue usado con otra solicitud.', 'LCD_IDEMPOTENCY_KEY_REUSED', 409);
            }

            return response()
                ->json($cached['body'] ?? [], (int) ($cached['status'] ?? 200))
                ->header('Idempotency-Replayed', 'true');
        }

        $lock = Cache::lock($cacheKey.':lock', 30);

        try {
            if (! $lock->get()) {
                return $this->error($request, 'La misma operacion ya esta en proceso.', 'LCD_IDEMPOTENCY_IN_PROGRESS', 409);
            }

            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()
                    ->json($cached['body'] ?? [], (int) ($cached['status'] ?? 200))
                    ->header('Idempotency-Replayed', 'true');
            }

            $response = $next($request);
            if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'body' => json_decode((string) $response->getContent(), true),
                ], now()->addDay());
            }

            return $response;
        } catch (LockTimeoutException) {
            return $this->error($request, 'La misma operacion ya esta en proceso.', 'LCD_IDEMPOTENCY_IN_PROGRESS', 409);
        } finally {
            optional($lock)->release();
        }
    }

    private function fingerprint(Request $request, int $userId): string
    {
        $safeInput = collect($request->input())->except([
            'otp', 'OTP', 'password', 'password_confirmation', 'token', 'access_token',
        ])->sortKeys()->all();

        return $this->canonical->hash([
            'method' => $request->method(),
            'path' => $request->path(),
            'user_id' => $userId,
            'input' => $safeInput,
            'files' => $this->fileFingerprints($request->allFiles()),
        ]);
    }

    /**
     * Include only non-reversible file metadata and content hashes. This keeps
     * multipart retries deterministic without persisting filenames or bytes in
     * the idempotency cache.
     *
     * @param  array<string|int, UploadedFile|array<mixed>>  $files
     * @return array<string|int, mixed>
     */
    private function fileFingerprints(array $files): array
    {
        $fingerprints = [];

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $fingerprints[$key] = $this->fileFingerprints($file);

                continue;
            }

            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                $fingerprints[$key] = ['valid' => false];

                continue;
            }

            $path = $file->getRealPath();
            $fingerprints[$key] = [
                'valid' => true,
                'sha256' => is_string($path) && is_file($path) ? hash_file('sha256', $path) : null,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => strtolower($file->getClientOriginalExtension()),
            ];
        }

        ksort($fingerprints);

        return $fingerprints;
    }

    private function error(Request $request, string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'details' => [],
            'correlation_id' => $request->attributes->get('lcd_correlation_id'),
        ], $status);
    }
}
