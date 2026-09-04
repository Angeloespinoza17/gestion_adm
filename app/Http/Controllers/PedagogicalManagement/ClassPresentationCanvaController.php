<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Enums\PedagogicalManagement\CanvaPublicationStatus;
use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use App\Http\Controllers\Controller;
use App\Jobs\PedagogicalManagement\PublishClassPresentationToCanvaJob;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaApiClient;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaAutofillAccessPolicy;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaConnectionLocator;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassPresentationCanvaController extends Controller
{
    public function sync(
        Request $request,
        ClassPresentation $presentation,
        CanvaConnectionLocator $connections,
        CanvaAutofillAccessPolicy $autofillAccess,
    ): JsonResponse {
        $this->authorize('view', $presentation);
        $this->assertOwner($request, $presentation);
        if ($this->provider($presentation) !== 'canva') {
            throw new CanvaIntegrationException('Esta presentación no fue configurada para Canva.', 'CANVA_PROVIDER_INVALID', 422);
        }
        if ($presentation->status !== ClassPresentationStatus::Ready || ! is_array($presentation->deck_json)) {
            throw new CanvaIntegrationException('El contenido todavía no está listo para enviarlo a Canva.', 'CANVA_CONTENT_NOT_READY', 409);
        }
        if ($presentation->canva_failure_code === 'CANVA_SUBMISSION_UNCONFIRMED') {
            throw new CanvaIntegrationException(
                'Canva no confirmó la creación anterior. Revisa tus diseños antes de iniciar otro para evitar duplicados.',
                'CANVA_SUBMISSION_UNCONFIRMED',
                409,
            );
        }

        $connection = $presentation->canvaConnection;
        if (! $connection || $connection->status !== CanvaConnectionStatus::Active) {
            $connection = $connections->active($request->user(), (int) $presentation->school_id);
        }
        $autofillAccess->assertAvailable($connection);

        $alreadyComplete = $presentation->canva_status === CanvaPublicationStatus::Success && $presentation->canva_design_id;
        if (! $alreadyComplete) {
            DB::transaction(function () use ($presentation, $connection): void {
                $locked = ClassPresentation::query()->whereKey($presentation->id)->lockForUpdate()->firstOrFail();
                $knownRemoteFailure = $locked->canva_status === CanvaPublicationStatus::Failed
                    && $locked->canva_autofill_job_id
                    && (str_starts_with((string) $locked->canva_failure_code, 'CANVA_REMOTE_JOB_')
                        || $locked->canva_failure_code === 'CANVA_TRIAL_QUOTA_EXCEEDED');
                $locked->forceFill([
                    'presentation_provider' => 'canva',
                    'canva_connection_id' => $connection->id,
                    'canva_brand_template_id' => $locked->canva_brand_template_id ?: data_get($locked->configuration, 'canva_template_id'),
                    'canva_brand_template_title' => $locked->canva_brand_template_title ?: data_get($locked->configuration, 'canva_template_title'),
                    'canva_status' => $knownRemoteFailure ? CanvaPublicationStatus::Pending : ($locked->canva_status ?: CanvaPublicationStatus::Pending),
                    'canva_autofill_job_id' => $knownRemoteFailure ? null : $locked->canva_autofill_job_id,
                    'canva_failure_code' => $knownRemoteFailure ? null : $locked->canva_failure_code,
                    'canva_failure_message' => $knownRemoteFailure ? null : $locked->canva_failure_message,
                    'canva_completed_at' => $knownRemoteFailure ? null : $locked->canva_completed_at,
                ])->save();
            }, 3);
            PublishClassPresentationToCanvaJob::dispatch($presentation->id)->afterCommit();
        }

        $presentation->refresh();

        return response()->json(['data' => $this->status($presentation)], $alreadyComplete ? 200 : 202);
    }

    public function editLink(
        Request $request,
        ClassPresentation $presentation,
        CanvaApiClient $client,
        CanvaTokenService $tokens,
    ): JsonResponse {
        $this->authorize('view', $presentation);
        $this->assertOwner($request, $presentation);
        if (! $presentation->canva_design_id || $presentation->canva_status !== CanvaPublicationStatus::Success) {
            throw new CanvaIntegrationException('El diseño Canva todavía no está disponible.', 'CANVA_DESIGN_NOT_READY', 409);
        }
        $connection = $presentation->canvaConnection;
        if (! $connection
            || $connection->status !== CanvaConnectionStatus::Active
            || (int) $connection->user_id !== (int) $request->user()->id
            || (int) $connection->school_id !== (int) $presentation->school_id
        ) {
            throw new CanvaIntegrationException('Vuelve a conectar la cuenta que creó este diseño.', 'CANVA_REAUTHORIZATION_REQUIRED', 409);
        }

        if (! $presentation->canva_edit_url
            || ! $presentation->canva_urls_refreshed_at
            || $presentation->canva_urls_refreshed_at->lt(now()->subHours(12))
        ) {
            $payload = $client->design($tokens->accessToken($connection), (string) $presentation->canva_design_id);
            $design = (array) ($payload['design'] ?? []);
            if ((string) ($design['id'] ?? '') !== (string) $presentation->canva_design_id) {
                throw new CanvaIntegrationException('Canva devolvió un diseño distinto al solicitado.', 'CANVA_DESIGN_RESPONSE_INVALID', 502);
            }
            $ownerUser = trim((string) data_get($design, 'owner.user_id'));
            $ownerTeam = trim((string) data_get($design, 'owner.team_id'));
            if (($ownerUser !== '' && $ownerUser !== $connection->canva_user_id)
                || ($ownerTeam !== '' && $ownerTeam !== $connection->canva_team_id)
            ) {
                throw new CanvaIntegrationException('La cuenta conectada no es propietaria de este diseño.', 'CANVA_DESIGN_OWNER_MISMATCH', 403);
            }
            $editUrl = $this->safeCanvaUrl(data_get($design, 'urls.edit_url'));
            if (! $editUrl) {
                throw new CanvaIntegrationException('Canva no devolvió un enlace de edición seguro.', 'CANVA_EDIT_URL_INVALID', 502);
            }
            $thumbnailUrl = $this->safeCanvaUrl(data_get($design, 'thumbnail.url'));
            $presentation->forceFill([
                'canva_design_url' => $this->safeCanvaUrl($design['url'] ?? null) ?: $presentation->canva_design_url,
                'canva_edit_url' => $editUrl,
                'canva_view_url' => $this->safeCanvaUrl(data_get($design, 'urls.view_url')),
                'canva_thumbnail_url' => $thumbnailUrl,
                'canva_thumbnail_expires_at' => $thumbnailUrl ? now()->addMinutes(15) : null,
                'canva_urls_refreshed_at' => now(),
            ])->save();
        }

        return response()->json(['data' => [
            'design_id' => $presentation->canva_design_id,
            'url' => $presentation->canva_edit_url,
            'edit_url' => $presentation->canva_edit_url,
            'view_url' => $presentation->canva_view_url,
            'expires_at' => $presentation->canva_urls_refreshed_at?->copy()->addDays(30)->toIso8601String(),
        ]]);
    }

    private function assertOwner(Request $request, ClassPresentation $presentation): void
    {
        abort_unless((int) $presentation->user_id === (int) $request->user()->id, 403, 'Solo el autor puede operar su conexión personal de Canva.');
    }

    private function provider(ClassPresentation $presentation): string
    {
        return (string) ($presentation->presentation_provider ?: data_get($presentation->configuration, 'presentation_provider', 'powerpoint'));
    }

    /** @return array<string,mixed> */
    private function status(ClassPresentation $presentation): array
    {
        return [
            'status' => $presentation->canva_status?->value,
            'autofill_job_id' => $presentation->canva_autofill_job_id,
            'design_id' => $presentation->canva_design_id,
            'failure_code' => $presentation->canva_failure_code,
            'failure_message' => $presentation->canva_failure_message,
            'submitted_at' => $presentation->canva_submitted_at?->toIso8601String(),
            'completed_at' => $presentation->canva_completed_at?->toIso8601String(),
        ];
    }

    private function safeCanvaUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($url === '' || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return null;
        }

        return ($host === 'canva.com' || str_ends_with($host, '.canva.com')) ? mb_substr($url, 0, 4000) : null;
    }
}
