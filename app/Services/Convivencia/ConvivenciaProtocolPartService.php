<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaProtocolPartService
{
    public function __construct(private readonly ConvivenciaSupportService $supportService) {}

    public function store(array $payload, User $user): ConvivenciaProtocolPart
    {
        return DB::transaction(function () use ($payload, $user) {
            $part = new ConvivenciaProtocolPart;
            $this->fill($part, $payload, $user, true);
            $part->save();

            return $this->load($part);
        });
    }

    public function update(ConvivenciaProtocolPart $part, array $payload, User $user): ConvivenciaProtocolPart
    {
        return DB::transaction(function () use ($part, $payload, $user) {
            $part = ConvivenciaProtocolPart::query()->lockForUpdate()->findOrFail($part->id);
            $this->fill($part, $payload, $user, false);
            $part->save();

            $protocolIds = $part->links()->distinct()->pluck('protocol_id');
            ConvivenciaProtocol::query()->whereIn('id', $protocolIds)->lockForUpdate()->get()->each(function ($protocol) use ($part, $user) {
                $protocol->forceFill([
                    'revision' => ((int) $protocol->revision) + 1,
                    'updated_by' => $user->id,
                ])->save();
                $this->supportService->logStatus(
                    $protocol,
                    $protocol->status,
                    $protocol->status,
                    $user,
                    "Parte {$part->code} actualizada. Revisión {$protocol->revision}.",
                    'definition_updated'
                );
            });

            return $this->load($part);
        });
    }

    public function archive(ConvivenciaProtocolPart $part, User $user): void
    {
        DB::transaction(function () use ($part, $user) {
            $part = ConvivenciaProtocolPart::query()->lockForUpdate()->findOrFail($part->id);
            $protocolIds = $part->links()->distinct()->pluck('protocol_id');
            $protocols = ConvivenciaProtocol::query()->whereIn('id', $protocolIds)->lockForUpdate()->get();

            $part->links()->delete();
            $part->forceFill(['active' => false, 'updated_by' => $user->id])->save();
            $part->delete();

            $protocols->each(function ($protocol) use ($part, $user) {
                $protocol->forceFill([
                    'revision' => ((int) $protocol->revision) + 1,
                    'updated_by' => $user->id,
                ])->save();
                $this->supportService->logStatus(
                    $protocol,
                    $protocol->status,
                    $protocol->status,
                    $user,
                    "Parte {$part->code} archivada y desvinculada. Revisión {$protocol->revision}.",
                    'definition_updated'
                );
            });
        });
    }

    private function fill(ConvivenciaProtocolPart $part, array $payload, User $user, bool $creating): void
    {
        $part->fill([
            'category' => $payload['category'],
            'code' => $payload['code'],
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'instructions' => $payload['instructions'] ?? null,
            'responsible_label' => $payload['responsible_label'] ?? null,
            'population_scope' => $payload['population_scope'] ?? null,
            'legal_reference' => $payload['legal_reference'] ?? null,
            'deadline_value' => $payload['deadline_value'] ?? null,
            'deadline_unit' => $payload['deadline_unit'] ?? null,
            'deadline_anchor' => $payload['deadline_anchor'] ?? null,
            'requires_evidence' => (bool) ($payload['requires_evidence'] ?? false),
            'active' => (bool) ($payload['active'] ?? true),
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? true),
            'metadata' => $payload['metadata'] ?? null,
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $part->created_by = $user->id;
        }
    }

    private function load(ConvivenciaProtocolPart $part): ConvivenciaProtocolPart
    {
        return $part->fresh([
            'createdBy:id,name',
            'updatedBy:id,name',
            'links.protocol:id,name,revision',
            'links.step:id,protocol_id,stage_name,step_order',
        ])->loadCount('links');
    }
}
