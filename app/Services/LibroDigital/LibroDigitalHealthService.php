<?php

namespace App\Services\LibroDigital;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class LibroDigitalHealthService
{
    /** @return array{healthy: bool, checks: array<int, array<string, mixed>>} */
    public function check(): array
    {
        $checks = [
            $this->probe('database', fn () => DB::select('select 1')),
            $this->probe('queue', fn () => config('queue.default') !== null),
            $this->probe('private_storage', function (): bool {
                $disk = Storage::disk((string) config('libro_digital.storage.disk', 'local'));
                $path = trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/').'/.health-'.Str::ulid();
                $disk->put($path, 'health');
                $ok = $disk->exists($path);
                $disk->delete($path);

                return $ok;
            }),
            $this->probe('app_encryption_key', fn () => filled(config('app.key'))),
            $this->probe('identity_verifier_config', fn () => config('libro_digital.identity_verifier.driver') !== 'disabled'
                && filled(config('libro_digital.identity_verifier.healthcheck_url'))),
            $this->probe('ede_digest', fn () => preg_match('/^sha256:[a-f0-9]{64}$/', (string) config('libro_digital.ede.validator_digest')) === 1),
            $this->probe('ede_runtime', function (): bool {
                if (! config('libro_digital.ede.enabled')) {
                    return false;
                }
                $process = new Process(['docker', 'version', '--format', '{{.Server.Version}}'], timeout: 3);
                $process->run();

                return $process->isSuccessful();
            }),
        ];

        return ['healthy' => collect($checks)->where('required', true)->every('passed'), 'checks' => $checks];
    }

    private function probe(string $code, callable $probe): array
    {
        $optional = in_array($code, ['identity_verifier_config', 'ede_digest', 'ede_runtime'], true);
        try {
            $start = hrtime(true);
            $passed = (bool) $probe();

            return ['code' => $code, 'passed' => $passed, 'required' => ! $optional, 'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2)];
        } catch (Throwable) {
            return ['code' => $code, 'passed' => false, 'required' => ! $optional, 'duration_ms' => null];
        }
    }
}
