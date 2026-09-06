<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaAttachment;
use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Models\Convivencia\ConvivenciaProtocolPartLink;
use App\Models\Convivencia\ConvivenciaProtocolStep;
use App\Models\Convivencia\ConvivenciaStatusLog;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaProtocolService;
use Database\Seeders\ConvivenciaRice2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

class ConvivenciaRice2026SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_rice_seeder_is_safe_idempotent_and_preserves_runtime_traceability(): void
    {
        $actor = User::factory()->create(['active' => true]);

        $this->seed(ConvivenciaRice2026Seeder::class);
        $this->assertRiceIntegrity();

        $protocol = ConvivenciaProtocol::query()->where('code', 'RICE-P01')->firstOrFail();
        $part = ConvivenciaProtocolPart::query()->where('code', 'BASE')->firstOrFail();
        $step = $protocol->steps()->firstOrFail();
        $activation = app(ConvivenciaProtocolService::class)->activate([
            'protocol_id' => $protocol->id,
        ], $actor);
        $case = ConvivenciaCase::query()->create([
            'folio' => 'CONV-RICE-SENTINEL',
            'opened_at' => now(),
            'origin' => 'otro',
            'status' => 'abierto',
            'initial_report' => 'Registro operacional centinela que el instalador normativo no debe modificar.',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $attachment = ConvivenciaAttachment::query()->forceCreate([
            'attachable_type' => ConvivenciaCase::class,
            'attachable_id' => $case->id,
            'case_id' => $case->id,
            'category' => 'evidencia',
            'confidentiality_level' => 'confidencial',
            'is_sensitive' => true,
            'file_path' => 'convivencia/test/sentinel.pdf',
            'original_name' => 'sentinel.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'uploaded_by' => $actor->id,
        ]);
        $customProtocol = ConvivenciaProtocol::query()->create([
            'code' => 'CUSTOM-NO-RICE',
            'revision' => 7,
            'name' => 'Protocolo local no administrado por RICE',
            'status' => 'borrador',
            'is_sensitive' => false,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $sourceTraceBefore = $this->runtimeTrace($activation);
        $stepIdsBefore = $this->stepIds();
        $linkIdsBefore = $this->linkIds();

        $protocol->forceFill(['name' => 'DESVIO TEMPORAL'])->save();
        $step->forceFill(['stage_name' => 'DESVIO TEMPORAL'])->save();
        $part->forceFill(['title' => 'DESVIO TEMPORAL'])->save();

        $this->travel(2)->minutes();
        $this->seed(ConvivenciaRice2026Seeder::class);
        $this->assertRiceIntegrity();

        $this->assertSame($stepIdsBefore, $this->stepIds());
        $this->assertSame($linkIdsBefore, $this->linkIds());
        $this->assertSame($sourceTraceBefore, $this->runtimeTrace($activation->refresh()));
        $this->assertDatabaseHas('convivencia_cases', ['id' => $case->id, 'folio' => 'CONV-RICE-SENTINEL']);
        $this->assertDatabaseHas('convivencia_attachments', ['id' => $attachment->id, 'case_id' => $case->id]);
        $this->assertDatabaseHas('convivencia_protocol_activations', ['id' => $activation->id, 'protocol_id' => $protocol->id]);
        $this->assertDatabaseHas('convivencia_protocols', [
            'id' => $customProtocol->id,
            'code' => 'CUSTOM-NO-RICE',
            'revision' => 7,
        ]);
        $this->assertNotSame('DESVIO TEMPORAL', $protocol->refresh()->name);
        $this->assertNotSame('DESVIO TEMPORAL', $step->refresh()->stage_name);
        $this->assertNotSame('DESVIO TEMPORAL', $part->refresh()->title);

        $signatureBeforeNoOp = $this->definitionSignature();
        $operationalCountsBeforeNoOp = $this->operationalCounts();
        $this->travel(2)->minutes();
        $this->seed(ConvivenciaRice2026Seeder::class);

        $this->assertSame($signatureBeforeNoOp, $this->definitionSignature());
        $this->assertSame($operationalCountsBeforeNoOp, $this->operationalCounts());
        $this->assertSame($sourceTraceBefore, $this->runtimeTrace($activation->refresh()));
    }

    public function test_rice_seeder_fails_closed_outside_local_or_testing(): void
    {
        User::factory()->create(['active' => true]);
        $originalEnvironment = $this->app->environment();
        $this->app['env'] = 'production';

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('solo puede ejecutarse con APP_ENV=local');
            $this->app->make(ConvivenciaRice2026Seeder::class)->run();
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_rice_seeder_does_not_restore_an_archived_part(): void
    {
        User::factory()->create(['active' => true]);
        $this->seed(ConvivenciaRice2026Seeder::class);
        $part = ConvivenciaProtocolPart::query()->where('code', 'BASE')->firstOrFail();
        $part->delete();

        try {
            $this->app->make(ConvivenciaRice2026Seeder::class)->run();
            $this->fail('La carga debio rechazar la parte archivada.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('parte RICE BASE esta archivada', $exception->getMessage());
        }

        $this->assertTrue(ConvivenciaProtocolPart::withTrashed()->findOrFail($part->id)->trashed());
    }

    public function test_rice_seeder_does_not_restore_an_archived_protocol(): void
    {
        User::factory()->create(['active' => true]);
        $this->seed(ConvivenciaRice2026Seeder::class);
        $protocol = ConvivenciaProtocol::query()->where('code', 'RICE-P01')->firstOrFail();
        $protocol->delete();
        $logCount = ConvivenciaStatusLog::query()->count();

        try {
            $this->app->make(ConvivenciaRice2026Seeder::class)->run();
            $this->fail('La carga debio rechazar el protocolo archivado.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('protocolo RICE RICE-P01 esta archivado', $exception->getMessage());
        }

        $this->assertTrue(ConvivenciaProtocol::withTrashed()->findOrFail($protocol->id)->trashed());
        $this->assertSame($logCount, ConvivenciaStatusLog::query()->count());
    }

    private function assertRiceIntegrity(): void
    {
        $config = config('convivencia_rice_2026');
        $protocolCodes = array_column($config['protocols'], 'code');
        $partCodes = array_column($config['parts'], 'code');
        $protocols = ConvivenciaProtocol::query()->whereIn('code', $protocolCodes)->get(['id', 'code']);
        $steps = ConvivenciaProtocolStep::query()
            ->whereIn('protocol_id', $protocols->pluck('id'))
            ->orderBy('protocol_id')
            ->orderBy('step_order')
            ->get();
        $links = ConvivenciaProtocolPartLink::query()
            ->whereIn('protocol_id', $protocols->pluck('id'))
            ->get();

        $this->assertCount(17, $protocols);
        $this->assertSame(28, ConvivenciaProtocolPart::query()->whereIn('code', $partCodes)->count());
        $this->assertCount(101, $steps);
        $this->assertCount(209, $links);
        $this->assertCount(207, $links->whereNotNull('protocol_step_id'));
        $this->assertCount(2, $links->whereNull('protocol_step_id'));
        $this->assertCount(209, $links->unique(fn (ConvivenciaProtocolPartLink $link): string => implode('|', [
            $link->protocol_id,
            $link->protocol_step_id ?: 'global',
            $link->protocol_part_id,
        ])));

        foreach ($steps->groupBy('protocol_id') as $protocolSteps) {
            $this->assertSame(range(1, $protocolSteps->count()), $protocolSteps->pluck('step_order')->all());
            $this->assertSame($protocolSteps->count(), $protocolSteps->pluck('code')->filter()->unique()->count());
        }

        $stepProtocols = $steps->pluck('protocol_id', 'id');
        foreach ($links->whereNotNull('protocol_step_id') as $link) {
            $this->assertSame((int) $link->protocol_id, (int) $stepProtocols->get($link->protocol_step_id));
        }
    }

    /** @return array<string, mixed> */
    private function definitionSignature(): array
    {
        $config = config('convivencia_rice_2026');
        $protocolCodes = array_column($config['protocols'], 'code');
        $partCodes = array_column($config['parts'], 'code');
        $protocols = ConvivenciaProtocol::query()
            ->whereIn('code', $protocolCodes)
            ->orderBy('id')
            ->get(['id', 'code', 'revision', 'updated_at']);
        $protocolIds = $protocols->pluck('id');

        return [
            'protocols' => $protocols->map->getAttributes()->all(),
            'parts' => ConvivenciaProtocolPart::query()
                ->whereIn('code', $partCodes)
                ->orderBy('id')
                ->get(['id', 'code', 'updated_at'])
                ->map->getAttributes()
                ->all(),
            'steps' => ConvivenciaProtocolStep::query()
                ->whereIn('protocol_id', $protocolIds)
                ->orderBy('id')
                ->get(['id', 'protocol_id', 'code', 'step_order', 'updated_at'])
                ->map->getAttributes()
                ->all(),
            'links' => ConvivenciaProtocolPartLink::query()
                ->whereIn('protocol_id', $protocolIds)
                ->orderBy('id')
                ->get(['id', 'protocol_id', 'protocol_step_id', 'protocol_part_id', 'sort_order', 'is_required', 'updated_at'])
                ->map->getAttributes()
                ->all(),
            'status_logs' => ConvivenciaStatusLog::query()
                ->where('loggable_type', ConvivenciaProtocol::class)
                ->whereIn('loggable_id', $protocolIds)
                ->count(),
        ];
    }

    /** @return array<string, int> */
    private function operationalCounts(): array
    {
        return [
            'cases' => ConvivenciaCase::query()->withTrashed()->count(),
            'activations' => ConvivenciaProtocolActivation::query()->count(),
            'attachments' => ConvivenciaAttachment::query()->count(),
        ];
    }

    /** @return array<string, array<int, int>> */
    private function stepIds(): array
    {
        return $this->riceProtocols()
            ->mapWithKeys(fn (ConvivenciaProtocol $protocol): array => [
                $protocol->code => $protocol->steps()->orderBy('step_order')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ])
            ->all();
    }

    /** @return array<string, array<int, int>> */
    private function linkIds(): array
    {
        return $this->riceProtocols()
            ->mapWithKeys(fn (ConvivenciaProtocol $protocol): array => [
                $protocol->code => $protocol->partLinks()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ])
            ->all();
    }

    /** @return Collection<int, ConvivenciaProtocol> */
    private function riceProtocols(): Collection
    {
        return ConvivenciaProtocol::query()
            ->whereIn('code', array_column(config('convivencia_rice_2026.protocols'), 'code'))
            ->orderBy('code')
            ->get();
    }

    /** @return array<string, mixed> */
    private function runtimeTrace(ConvivenciaProtocolActivation $activation): array
    {
        $activation->load(['runtimeSteps', 'runtimeParts']);

        return [
            'activation' => $activation->only([
                'id',
                'protocol_id',
                'current_step_id',
                'current_activation_step_id',
                'protocol_snapshot',
            ]),
            'steps' => $activation->runtimeSteps->map(fn ($step): array => $step->only([
                'id',
                'source_protocol_step_id',
                'snapshot',
            ]))->all(),
            'parts' => $activation->runtimeParts->map(fn ($part): array => $part->only([
                'id',
                'source_link_id',
                'protocol_part_id',
                'snapshot',
            ]))->all(),
        ];
    }
}
