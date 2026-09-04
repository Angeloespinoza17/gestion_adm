<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Contracts\PedagogicalManagement\ClassPresentationContentGenerator;
use App\DTO\PedagogicalManagement\GeneratedPresentationContent;
use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use App\Models\PedagogicalManagement\ClassPresentation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

class OpenAiClassPresentationService implements ClassPresentationContentGenerator
{
    public function __construct(
        private readonly PresentationPromptBuilder $prompt,
        private readonly PresentationSchema $schema,
        private readonly PresentationDeckValidator $validator,
    ) {}

    public function isConfigured(): bool
    {
        return trim((string) config('class_presentations.openai.api_key')) !== ''
            && trim((string) config('class_presentations.openai.model')) !== '';
    }

    public function generate(ClassPresentation $presentation, array $referenceMaterials): GeneratedPresentationContent
    {
        if (! $this->isConfigured()) {
            throw new ClassPresentationGenerationException('La integración OpenAI del Generador de clases no está configurada.', 'OPENAI_NOT_CONFIGURED', 503);
        }

        try {
            $response = $this->client()->post($this->url('/responses'), $this->payload($presentation, $referenceMaterials));
        } catch (ConnectionException) {
            throw new ClassPresentationGenerationException('OpenAI no respondió dentro del tiempo disponible.', 'OPENAI_TIMEOUT', 504);
        } catch (Throwable) {
            throw new ClassPresentationGenerationException('No fue posible conectar con OpenAI.', 'OPENAI_CONNECTION_FAILED', 502);
        }

        if (! $response->successful()) {
            $code = $response->status() === 429 ? 'OPENAI_RATE_LIMITED' : 'OPENAI_HTTP_ERROR';
            $message = $response->status() === 429
                ? 'OpenAI alcanzó temporalmente su límite. La cola reintentará la generación.'
                : 'OpenAI rechazó la solicitud de generación.';
            throw new ClassPresentationGenerationException($message, $code, $response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new ClassPresentationGenerationException('OpenAI devolvió una respuesta ilegible.', 'OPENAI_RESPONSE_INVALID', 502);
        }
        if (($payload['status'] ?? null) === 'incomplete') {
            $reason = (string) data_get($payload, 'incomplete_details.reason', 'unknown');
            throw new ClassPresentationGenerationException("OpenAI dejó la respuesta incompleta ({$reason}).", 'OPENAI_RESPONSE_INCOMPLETE', 502);
        }
        if (($payload['status'] ?? null) === 'failed' || isset($payload['error'])) {
            throw new ClassPresentationGenerationException('OpenAI informó un error al generar el contenido.', 'OPENAI_RESPONSE_FAILED', 502);
        }

        foreach ((array) ($payload['output'] ?? []) as $output) {
            foreach ((array) ($output['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new ClassPresentationGenerationException('OpenAI no pudo atender el contenido solicitado por sus salvaguardas.', 'OPENAI_REFUSAL', 422);
                }
            }
        }
        $text = collect($payload['output'] ?? [])->flatMap(fn ($item): array => (array) ($item['content'] ?? []))
            ->first(fn ($content): bool => is_array($content) && ($content['type'] ?? null) === 'output_text')['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw new ClassPresentationGenerationException('OpenAI no devolvió el JSON de la presentación.', 'OPENAI_OUTPUT_MISSING', 502);
        }
        try {
            $deck = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ClassPresentationGenerationException('OpenAI devolvió JSON inválido.', 'OPENAI_OUTPUT_INVALID', 502);
        }
        if (! is_array($deck)) {
            throw new ClassPresentationGenerationException('OpenAI devolvió una estructura inválida.', 'OPENAI_OUTPUT_INVALID', 502);
        }
        $this->validator->validate($presentation, $deck);

        return new GeneratedPresentationContent(
            $deck,
            isset($payload['id']) ? (string) $payload['id'] : null,
            (string) ($payload['model'] ?? $presentation->model ?? config('class_presentations.openai.model')),
            is_array($payload['usage'] ?? null) ? $payload['usage'] : [],
        );
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('class_presentations.openai.api_key'))
            ->acceptJson()
            ->timeout(max(30, (int) config('class_presentations.openai.timeout_seconds', 300)))
            ->retry(2, 1000, throw: false);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('class_presentations.openai.base_url'), '/').'/'.ltrim($path, '/');
    }

    /** @param list<array{name:string,content:string}> $referenceMaterials @return array<string,mixed> */
    private function payload(ClassPresentation $presentation, array $referenceMaterials): array
    {
        $slideCount = (int) data_get($presentation->configuration, 'slide_count');
        $payload = [
            'model' => (string) config('class_presentations.openai.model', 'gpt-5.6'),
            'store' => false,
            'instructions' => $this->prompt->instructions(),
            'input' => [[
                'role' => 'user',
                'content' => [['type' => 'input_text', 'text' => $this->prompt->input($presentation, $referenceMaterials)]],
            ]],
            'max_output_tokens' => (int) config('class_presentations.openai.max_output_tokens', 30000),
            'reasoning' => ['effort' => (string) config('class_presentations.openai.reasoning_effort', 'medium')],
            'safety_identifier' => hash_hmac('sha256', 'class-presentations:user:'.$presentation->user_id, (string) config('app.key')),
            'text' => [
                'format' => [
                    'type' => 'json_schema', 'name' => 'class_presentation', 'strict' => true,
                    'schema' => $this->schema->build($slideCount),
                ],
            ],
        ];
        if ((bool) data_get($presentation->configuration, 'web_research', false)) {
            $payload['tools'] = [['type' => 'web_search', 'search_context_size' => 'medium']];
            $payload['include'] = ['web_search_call.action.sources'];
        }

        return $payload;
    }
}
