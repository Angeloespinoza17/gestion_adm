<?php

namespace App\Jobs\PedagogicalManagement;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Enums\PedagogicalManagement\CanvaPublicationStatus;
use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use App\Models\PedagogicalManagement\CanvaConnection;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaApiClient;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaAutofillAccessPolicy;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTemplateFieldMapper;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTemplateService;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTokenService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublishClassPresentationToCanvaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 100;

    public int $timeout = 120;

    public int $uniqueFor = 1800;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public readonly int $deadlineTimestamp;

    public function __construct(public readonly int $presentationId)
    {
        $this->deadlineTimestamp = now()
            ->addMinutes(max(5, (int) config('canva.autofill_deadline_minutes', 15)))
            ->getTimestamp();
        $this->onQueue((string) config('class_presentations.generation.queue', 'class-presentations'));
    }

    public function uniqueId(): string
    {
        return 'class-presentation-canva:'.$this->presentationId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->setTimestamp($this->deadlineTimestamp);
    }

    public function handle(
        CanvaApiClient $client,
        CanvaTokenService $tokens,
        CanvaTemplateService $templates,
        CanvaTemplateFieldMapper $mapper,
        CanvaAutofillAccessPolicy $autofillAccess,
    ): void {
        $presentation = ClassPresentation::query()->find($this->presentationId);
        if (! $presentation || $this->provider($presentation) !== 'canva') {
            return;
        }
        if (now()->getTimestamp() >= $this->deadlineTimestamp) {
            $this->markFailed('CANVA_AUTOFILL_TIMEOUT', 'Canva no terminó el diseño dentro del tiempo disponible.');

            return;
        }

        try {
            $this->assertReady($presentation);
            $connection = $this->connection($presentation, $autofillAccess);
            $autofillData = null;
            if (! $presentation->canva_autofill_job_id
                && $presentation->canva_status !== CanvaPublicationStatus::Submitting
            ) {
                // Validate and map the remote dataset before claiming the non-idempotent
                // submission. A transient dataset error can then retry without leaving
                // the presentation in an ambiguous "submitted" state.
                $dataset = $templates->dataset($connection, (string) $presentation->canva_brand_template_id);
                $autofillData = $mapper->map($presentation, (array) $presentation->deck_json, $dataset);
            }
            $action = $this->claimAction();
            if ($action === 'complete' || $action === 'stop') {
                return;
            }
            if ($action === 'submit') {
                if (! is_array($autofillData)) {
                    throw new CanvaIntegrationException(
                        'No fue posible preparar el contenido para Canva.',
                        'CANVA_AUTOFILL_DATA_MISSING',
                        502,
                    );
                }
                $payload = $client->createAutofill(
                    $tokens->accessToken($connection),
                    (string) $presentation->canva_brand_template_id,
                    $autofillData,
                );
                $jobId = trim((string) data_get($payload, 'job.id'));
                if ($jobId === '') {
                    throw new CanvaIntegrationException('Canva no devolvió el identificador del trabajo.', 'CANVA_AUTOFILL_RESPONSE_INVALID', 502, true);
                }
                $this->storeRemoteJob($jobId);
                $remoteStatus = (string) data_get($payload, 'job.status');
                if ($remoteStatus === 'success') {
                    $this->storeSuccess(
                        (array) data_get($payload, 'job.result.design', []),
                        (array) data_get($payload, 'job.result.trial_information', []),
                    );

                    return;
                }
                if ($remoteStatus === 'failed') {
                    $this->storeRemoteFailure($payload);

                    return;
                }
                $this->release($this->pollDelay());

                return;
            }

            $presentation->refresh();
            $payload = $client->autofillJob(
                $tokens->accessToken($connection),
                (string) $presentation->canva_autofill_job_id,
            );
            $remoteStatus = (string) data_get($payload, 'job.status');
            if ($remoteStatus === 'in_progress') {
                $this->release($this->pollDelay());

                return;
            }
            if ($remoteStatus === 'failed') {
                $this->storeRemoteFailure($payload);

                return;
            }
            if ($remoteStatus !== 'success') {
                throw new CanvaIntegrationException('Canva devolvió un estado de trabajo desconocido.', 'CANVA_AUTOFILL_RESPONSE_INVALID', 502, true);
            }
            $this->storeSuccess(
                (array) data_get($payload, 'job.result.design', []),
                (array) data_get($payload, 'job.result.trial_information', []),
            );
        } catch (CanvaIntegrationException $exception) {
            if ($exception->retryable) {
                throw $exception;
            }
            $this->markFailed(
                $exception->failureCode,
                $exception->getMessage(),
                $exception->failureCode === 'CANVA_TRIAL_QUOTA_EXCEEDED',
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed(
            'CANVA_PUBLICATION_RETRIES_EXHAUSTED',
            'No fue posible completar el diseño en Canva después de los reintentos.',
        );
    }

    private function provider(ClassPresentation $presentation): string
    {
        return (string) ($presentation->presentation_provider ?: data_get($presentation->configuration, 'presentation_provider', 'powerpoint'));
    }

    private function assertReady(ClassPresentation $presentation): void
    {
        if ($presentation->status !== ClassPresentationStatus::Ready || ! is_array($presentation->deck_json)) {
            throw new CanvaIntegrationException('El contenido de la clase todavía no está listo para enviarlo a Canva.', 'CANVA_CONTENT_NOT_READY', 409);
        }
        if (! $presentation->canva_connection_id || trim((string) $presentation->canva_brand_template_id) === '') {
            throw new CanvaIntegrationException('La presentación no tiene una conexión y plantilla Canva válidas.', 'CANVA_CONFIGURATION_MISSING', 422);
        }
    }

    private function connection(
        ClassPresentation $presentation,
        CanvaAutofillAccessPolicy $autofillAccess,
    ): CanvaConnection {
        $connection = CanvaConnection::query()->find($presentation->canva_connection_id);
        if (! $connection
            || (int) $connection->school_id !== (int) $presentation->school_id
            || (int) $connection->user_id !== (int) $presentation->user_id
            || $connection->status !== CanvaConnectionStatus::Active
        ) {
            throw new CanvaIntegrationException('La cuenta de Canva debe volver a conectarse.', 'CANVA_REAUTHORIZATION_REQUIRED', 409);
        }
        $autofillAccess->assertAvailable($connection);

        return $connection;
    }

    private function claimAction(): string
    {
        return DB::transaction(function (): string {
            $locked = ClassPresentation::query()->whereKey($this->presentationId)->lockForUpdate()->firstOrFail();
            if ($locked->canva_status === CanvaPublicationStatus::Success && $locked->canva_design_id) {
                return 'complete';
            }
            if ($locked->canva_autofill_job_id) {
                if ($locked->canva_status !== CanvaPublicationStatus::InProgress) {
                    $locked->forceFill(['canva_status' => CanvaPublicationStatus::InProgress])->save();
                }

                return 'poll';
            }
            if ($locked->canva_status === CanvaPublicationStatus::Submitting) {
                $locked->forceFill([
                    'canva_status' => CanvaPublicationStatus::Failed,
                    'canva_failure_code' => 'CANVA_SUBMISSION_UNCONFIRMED',
                    'canva_failure_message' => 'Canva no confirmó si alcanzó a crear el diseño. Revisa la cuenta antes de volver a intentarlo.',
                    'canva_completed_at' => now(),
                ])->save();

                return 'stop';
            }
            $locked->forceFill([
                'canva_status' => CanvaPublicationStatus::Submitting,
                'canva_failure_code' => null,
                'canva_failure_message' => null,
                'canva_submitted_at' => now(),
                'canva_completed_at' => null,
            ])->save();

            return 'submit';
        }, 3);
    }

    private function storeRemoteJob(string $jobId): void
    {
        DB::transaction(function () use ($jobId): void {
            $locked = ClassPresentation::query()->whereKey($this->presentationId)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'canva_autofill_job_id' => $jobId,
                'canva_status' => CanvaPublicationStatus::InProgress,
                'canva_failure_code' => null,
                'canva_failure_message' => null,
            ])->save();
        }, 3);
    }

    /** @param array<string,mixed> $design @param array<string,mixed> $trialInformation */
    private function storeSuccess(array $design, array $trialInformation = []): void
    {
        $designId = trim((string) ($design['id'] ?? ''));
        if ($designId === '') {
            throw new CanvaIntegrationException('Canva no devolvió el diseño creado.', 'CANVA_AUTOFILL_RESPONSE_INVALID', 502, true);
        }
        $designUrl = $this->safeCanvaUrl($design['url'] ?? null);
        $editUrl = $this->safeCanvaUrl(data_get($design, 'urls.edit_url'));
        $viewUrl = $this->safeCanvaUrl(data_get($design, 'urls.view_url'));
        $thumbnailUrl = $this->safeCanvaUrl(data_get($design, 'thumbnail.url'));

        $trial = $this->trialInformation($trialInformation);

        DB::transaction(function () use ($designId, $designUrl, $editUrl, $viewUrl, $thumbnailUrl, $trial): void {
            $locked = ClassPresentation::query()->whereKey($this->presentationId)->lockForUpdate()->firstOrFail();
            $updates = [
                'canva_status' => CanvaPublicationStatus::Success,
                'canva_design_id' => $designId,
                'canva_design_url' => $designUrl,
                'canva_edit_url' => $editUrl,
                'canva_view_url' => $viewUrl,
                'canva_thumbnail_url' => $thumbnailUrl,
                'canva_thumbnail_expires_at' => $thumbnailUrl ? now()->addMinutes(15) : null,
                'canva_urls_refreshed_at' => now(),
                'canva_failure_code' => null,
                'canva_failure_message' => null,
                'canva_completed_at' => now(),
            ];
            $configuration = (array) $locked->configuration;
            if ($trial !== null) {
                $configuration['canva_trial'] = $trial;
                $updates['configuration'] = $configuration;
            } elseif (array_key_exists('canva_trial', $configuration)) {
                unset($configuration['canva_trial']);
                $updates['configuration'] = $configuration;
            }
            $locked->forceFill($updates)->save();
        }, 3);
    }

    private function markFailed(string $code, string $message, bool $trialExhausted = false): void
    {
        DB::transaction(function () use ($code, $message, $trialExhausted): void {
            $locked = ClassPresentation::query()->whereKey($this->presentationId)->lockForUpdate()->first();
            if (! $locked || $locked->canva_status === CanvaPublicationStatus::Success) {
                return;
            }
            $updates = [
                'canva_status' => CanvaPublicationStatus::Failed,
                'canva_failure_code' => mb_substr($code, 0, 100),
                'canva_failure_message' => mb_substr($message, 0, 1000),
                'canva_completed_at' => now(),
            ];
            if ($trialExhausted) {
                $configuration = (array) $locked->configuration;
                $configuration['canva_trial'] = [
                    'mode' => CanvaAutofillAccessPolicy::MODE_DEVELOPMENT_TRIAL,
                    'status' => 'exhausted',
                    'uses_remaining' => 0,
                    'observed_at' => now()->toIso8601String(),
                ];
                $updates['configuration'] = $configuration;
            }
            $locked->forceFill($updates)->save();
        }, 3);
    }

    /** @param array<string,mixed> $payload */
    private function storeRemoteFailure(array $payload): void
    {
        $remoteCode = strtoupper($this->safeCode((string) data_get($payload, 'job.error.code')));
        $trialExhausted = in_array($remoteCode, ['TRIAL_QUOTA_EXCEEDED', 'QUOTA_EXCEEDED'], true);
        $this->markFailed(
            $trialExhausted
                ? 'CANVA_TRIAL_QUOTA_EXCEEDED'
                : ($remoteCode !== '' ? 'CANVA_REMOTE_JOB_'.$remoteCode : 'CANVA_REMOTE_JOB_FAILED'),
            $trialExhausted
                ? 'La prueba de desarrollo de Autofill agotó su cuota. Se requiere Canva Enterprise para continuar.'
                : 'Canva no pudo aplicar el contenido a la plantilla seleccionada.',
            $trialExhausted,
        );
    }

    /** @param array<string,mixed> $value @return array<string,mixed>|null */
    private function trialInformation(array $value): ?array
    {
        $usesRemaining = is_numeric($value['uses_remaining'] ?? null)
            ? max(0, (int) $value['uses_remaining'])
            : null;
        $upgradeUrl = $this->safeCanvaUrl($value['upgrade_url'] ?? null);
        if ($usesRemaining === null && $upgradeUrl === null) {
            return null;
        }

        return array_filter([
            'mode' => CanvaAutofillAccessPolicy::MODE_DEVELOPMENT_TRIAL,
            'status' => $usesRemaining === 0 ? 'exhausted' : 'active',
            'uses_remaining' => $usesRemaining,
            'upgrade_url' => $upgradeUrl,
            'observed_at' => now()->toIso8601String(),
        ], fn (mixed $item): bool => $item !== null);
    }

    private function pollDelay(): int
    {
        $base = max(2, (int) config('canva.autofill_poll_seconds', 5));
        $attempt = $this->job ? max(1, $this->attempts()) : 1;

        return min(30, $base * (2 ** min(3, $attempt - 1)));
    }

    private function safeCode(string $value): string
    {
        return trim((string) preg_replace('/[^A-Z0-9_]+/i', '_', $value), '_');
    }

    private function safeCanvaUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($url === '' || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return null;
        }
        if ($host !== 'canva.com' && ! str_ends_with($host, '.canva.com')) {
            return null;
        }

        return mb_substr($url, 0, 4000);
    }
}
