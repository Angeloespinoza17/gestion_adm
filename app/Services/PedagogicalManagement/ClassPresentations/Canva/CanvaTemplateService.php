<?php

namespace App\Services\PedagogicalManagement\ClassPresentations\Canva;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use App\Models\PedagogicalManagement\CanvaConnection;
use Illuminate\Support\Facades\Cache;

class CanvaTemplateService
{
    public function __construct(
        private readonly CanvaApiClient $client,
        private readonly CanvaTokenService $tokens,
    ) {}

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function list(CanvaConnection $connection, array $filters): array
    {
        $token = $this->tokens->accessToken($connection);
        $key = 'canva:templates:'.$connection->id.':'.hash('sha256', json_encode($filters, JSON_UNESCAPED_UNICODE) ?: '');

        return Cache::remember($key, max(30, (int) config('canva.template_cache_seconds', 300)), fn (): array => $this->client->listBrandTemplates($token, $filters));
    }

    /** @return array<string,mixed> */
    public function dataset(CanvaConnection $connection, string $brandTemplateId): array
    {
        if ($connection->status !== CanvaConnectionStatus::Active) {
            throw new CanvaIntegrationException('La cuenta de Canva debe volver a conectarse.', 'CANVA_REAUTHORIZATION_REQUIRED', 409);
        }
        $token = $this->tokens->accessToken($connection);
        $key = 'canva:template-dataset:'.$connection->id.':'.hash('sha256', $brandTemplateId);

        return Cache::remember($key, max(30, (int) config('canva.template_cache_seconds', 300)), fn (): array => $this->client->brandTemplateDataset($token, $brandTemplateId));
    }

    public function clearConnectionCache(CanvaConnection $connection): void
    {
        // Las claves incorporan conexión y filtros; su TTL corto evita catálogos persistentes paralelos a Canva.
    }
}
