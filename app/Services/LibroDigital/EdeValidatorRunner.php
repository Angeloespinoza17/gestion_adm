<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\EdeExport;
use App\Models\LibroDigital\EdeValidationResult;
use App\Models\LibroDigital\EdeValidationRun;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class EdeValidatorRunner
{
    private const OPERATIONS = ['parse', 'insert', 'check'];

    public function __construct(
        private readonly Filesystem $files,
        private readonly AuditEventWriter $audit,
        private readonly EdeExportService $exports,
    ) {}

    public function run(EdeExport $export, string $operation, User $actor): EdeValidationRun
    {
        $this->assertConfigured($operation);
        $this->exports->assertCanValidate($export);

        $digest = (string) config('libro_digital.ede.validator_digest');
        $image = (string) config('libro_digital.ede.validator_image').'@'.$digest;
        [$export, $staging, $run] = DB::transaction(function () use ($export, $operation, $actor, $digest): array {
            $locked = EdeExport::query()->lockForUpdate()->findOrFail($export->id);
            $staging = $locked->files()->where('file_type', 'projection_staging')->latest('id')->first();
            if (! $staging || ! in_array($this->status($locked->status), ['generated', 'validation_failed', 'validation_queued'], true)) {
                throw new LibroDigitalException('La exportación no tiene una proyección generada para validar.', 'LCD_EDE_STAGING_REQUIRED', 409);
            }
            if ($locked->validationRuns()->where('status', 'running')->exists()) {
                throw new LibroDigitalException('Ya existe una validación EDE en ejecución.', 'LCD_EDE_VALIDATION_ALREADY_RUNNING', 409);
            }

            $run = EdeValidationRun::query()->create([
                'ede_export_id' => $locked->id,
                'validator_name' => 'EDE official container',
                'validator_version' => (string) config('libro_digital.ede.version'),
                'validator_image_digest' => $digest,
                'status' => 'running',
                'started_at' => now('UTC'),
                'run_by' => $actor->id,
            ]);
            if ($operation === 'check') {
                $locked->forceFill([
                    'status' => 'validating',
                    'validator_status' => 'running',
                    'lock_version' => ((int) $locked->lock_version) + 1,
                ])->save();
            }

            return [$locked, $staging, $run];
        }, 3);
        $export->loadMissing('school');

        $workingDirectory = storage_path('framework/lcd-ede/'.Str::ulid());
        $this->files->ensureDirectoryExists($workingDirectory, 0700, true);
        $this->files->ensureDirectoryExists($workingDirectory.'/output', 0700, true);

        try {
            $encrypted = Storage::disk((string) config('libro_digital.storage.disk', 'local'))->get($staging->private_path);
            $input = Crypt::decryptString($encrypted);
            $this->files->put($workingDirectory.'/input.json', $input, true);
            $arguments = $this->containerArguments($image, $operation, $workingDirectory);
            $process = new Process($arguments, timeout: (float) config('libro_digital.ede.timeout_seconds', 900));
            $process->run();

            $stdout = $this->sanitize($process->getOutput());
            $stderr = $this->sanitize($process->getErrorOutput());
            $report = $this->files->exists($workingDirectory.'/output/report.json')
                ? $this->files->get($workingDirectory.'/output/report.json')
                : $stdout;
            $reportCiphertext = Crypt::encryptString($report);
            $reportPath = trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/')
                .'/ede/'.$export->school->public_id.'/'.$export->public_id.'/validation-'.$run->public_id.'.report.enc';
            Storage::disk((string) config('libro_digital.storage.disk', 'local'))->put($reportPath, $reportCiphertext);
            $reportPassed = $operation === 'check' ? $this->reportPassed($report) : null;
            $passed = $process->isSuccessful() && $operation === 'check' && $reportPassed === true;

            $run->forceFill([
                'status' => $process->isSuccessful() ? 'completed' : 'failed',
                'completed_at' => now('UTC'),
                'exit_code' => $process->getExitCode(),
                'stdout_sanitized' => mb_strimwidth($stdout, 0, 100000),
                'stderr_sanitized' => mb_strimwidth($stderr, 0, 100000),
                'report_private_path' => $reportPath,
                'report_hash' => hash('sha256', $report),
            ])->save();

            if ($operation === 'check') {
                EdeValidationResult::query()->create([
                    'validation_run_id' => $run->id,
                    'ede_export_id' => $export->id,
                    'severity' => $passed ? 'info' : 'error',
                    'code' => $passed ? 'EDE_CHECK_PASSED' : 'EDE_CHECK_NOT_PASSED',
                    'message' => $passed
                        ? 'El reporte contractual del validador declaró un resultado satisfactorio.'
                        : 'El proceso o el reporte contractual del validador no declaró un resultado satisfactorio.',
                    'metadata' => [
                        'exit_code' => $process->getExitCode(),
                        'report_success' => $reportPassed,
                        'report_hash' => hash('sha256', $report),
                    ],
                    'created_at' => now('UTC'),
                ]);
            }

            $export->forceFill([
                'status' => $operation === 'check'
                    ? ($passed ? 'validated' : 'validation_failed')
                    : 'generated',
                'validator_status' => $operation === 'check'
                    ? ($passed ? 'passed' : 'failed')
                    : 'not_run',
                'validator_version' => $digest,
                'validated_at' => $passed ? now('UTC') : null,
                'error_summary' => $passed || $operation !== 'check'
                    ? ($process->isSuccessful() ? null : mb_strimwidth($stderr ?: 'El contenedor finalizó con error.', 0, 1800))
                    : 'El reporte contractual de check no declaró un resultado satisfactorio.',
                'lock_version' => ((int) $export->lock_version) + 1,
            ])->save();

            $this->audit->write(
                $passed ? 'lcd.ede_export.validated' : 'lcd.ede_validator.executed',
                $operation,
                $export,
                actor: $actor,
                schoolId: $export->school_id,
                academicYearId: $export->academic_year_id,
                after: ['export' => $export->public_id, 'run' => $run->public_id, 'operation' => $operation, 'exit_code' => $process->getExitCode(), 'report_hash' => $run->report_hash, 'digest' => $digest],
            );

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'completed_at' => now('UTC'),
                'stderr_sanitized' => 'Fallo del runner: '.mb_strimwidth($exception->getMessage(), 0, 1800),
            ])->save();
            $export->forceFill([
                'status' => $operation === 'check' ? 'validation_failed' : 'generated',
                'validator_status' => $operation === 'check' ? 'failed' : 'not_run',
                'error_summary' => 'Falló la ejecución controlada del validador.',
                'lock_version' => ((int) $export->lock_version) + 1,
            ])->save();
            throw $exception;
        } finally {
            // El staging descifrado solo vive durante la corrida aislada.
            $this->files->deleteDirectory($workingDirectory, preserve: false);
        }
    }

    /** @return array<int, string> */
    private function containerArguments(string $image, string $operation, string $workingDirectory): array
    {
        $template = (array) config('libro_digital.ede.commands.'.$operation, []);
        $operationArguments = array_map(
            fn ($argument) => str_replace(['{input}', '{output}'], ['/work/input.json', '/work/output'], (string) $argument),
            $template,
        );

        return [
            'docker', 'run', '--rm', '--network=none', '--read-only', '--cap-drop=ALL',
            '--security-opt=no-new-privileges', '--user', $this->containerUser(), '--pids-limit=128', '--memory=1g', '--cpus=1',
            '--log-driver=none', '--tmpfs', '/tmp:rw,noexec,nosuid,size=64m',
            '--mount', 'type=bind,src='.$workingDirectory.',dst=/work',
            $image,
            ...$operationArguments,
        ];
    }

    private function assertConfigured(string $operation): void
    {
        $digest = (string) config('libro_digital.ede.validator_digest');
        $command = config('libro_digital.ede.commands.'.$operation);
        if (! in_array($operation, self::OPERATIONS, true)) {
            throw new LibroDigitalException('Operación del contenedor no permitida.', 'LCD_EDE_OPERATION_NOT_ALLOWED', 422);
        }
        if (! config('libro_digital.ede.enabled') || ! config('libro_digital.ede.command_contract_verified')) {
            throw new LibroDigitalException('El runner EDE está bloqueado hasta verificar el contrato oficial.', 'COMPLIANCE_BLOCKER_EDE_RUNNER', 409);
        }
        if (! preg_match('/^sha256:[a-f0-9]{64}$/', $digest)) {
            throw new LibroDigitalException('El digest del contenedor EDE no es válido.', 'COMPLIANCE_BLOCKER_EDE_DIGEST', 409);
        }
        if (str_starts_with($this->containerUser(), '0:') || $this->containerUser() === '0') {
            throw new LibroDigitalException('El contenedor EDE no puede ejecutarse como root.', 'COMPLIANCE_BLOCKER_EDE_CONTAINER_USER', 409);
        }
        if (! is_array($command) || $command === [] || collect($command)->contains(fn ($part) => ! is_scalar($part) || str_contains((string) $part, "\0"))) {
            throw new LibroDigitalException('No existe un argv versionado para esta operación EDE.', 'COMPLIANCE_BLOCKER_EDE_COMMAND', 409);
        }
        if ($operation === 'check' && ! filled(config('libro_digital.ede.validation_report.success_path'))) {
            throw new LibroDigitalException('No se ha configurado cómo interpretar el reporte oficial de check.', 'COMPLIANCE_BLOCKER_EDE_REPORT_CONTRACT', 409);
        }
    }

    private function sanitize(string $value): string
    {
        $value = preg_replace('/\b\d{7,8}-?[0-9Kk]\b/u', '[RUN REDACTADO]', $value) ?? '';

        return preg_replace('/(?i)(otp|token|password)\s*[:=]\s*\S+/', '$1=[REDACTADO]', $value) ?? '';
    }

    private function containerUser(): string
    {
        $configured = trim((string) config('libro_digital.ede.validator_user'));
        if ($configured !== '') {
            if (! preg_match('/^\d+(?::\d+)?$/', $configured)) {
                throw new LibroDigitalException('El usuario del contenedor no es válido.', 'COMPLIANCE_BLOCKER_EDE_CONTAINER_USER', 409);
            }

            return $configured;
        }

        $uid = function_exists('posix_geteuid') ? (int) posix_geteuid() : 0;
        $gid = function_exists('posix_getegid') ? (int) posix_getegid() : 0;

        return $uid.':'.$gid;
    }

    private function reportPassed(string $report): bool
    {
        try {
            $decoded = json_decode($report, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        $path = (string) config('libro_digital.ede.validation_report.success_path');
        $expected = config('libro_digital.ede.validation_report.success_value', true);

        return data_get($decoded, $path) === $expected;
    }

    private function status(mixed $status): string
    {
        return $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
    }
}
