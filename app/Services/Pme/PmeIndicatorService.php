<?php

namespace App\Services\Pme;

use App\Models\Pme\PmeIndicator;
use App\Models\Pme\PmeIndicatorMeasurement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PmeIndicatorService
{
    public function __construct(
        private readonly PmeChangeLogService $changeLogService,
    ) {
    }

    public function store(array $payload, User $actor): PmeIndicator
    {
        return DB::transaction(function () use ($payload, $actor) {
            $indicator = PmeIndicator::query()->create(array_merge($payload, [
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'compliance_percentage' => $this->compliance($payload['current_value'] ?? null, $payload['target_value'] ?? null),
            ]));

            $this->changeLogService->record($indicator, 'creado', $actor, null, $indicator->toArray(), 'Indicador PME creado.');

            return $indicator->fresh(['objective', 'strategy', 'responsibleUser']);
        });
    }

    public function update(PmeIndicator $indicator, array $payload, User $actor): PmeIndicator
    {
        return DB::transaction(function () use ($indicator, $payload, $actor) {
            $before = $indicator->toArray();
            $indicator->fill(array_merge($payload, [
                'updated_by' => $actor->id,
                'compliance_percentage' => $this->compliance($payload['current_value'] ?? $indicator->current_value, $payload['target_value'] ?? $indicator->target_value),
            ]));
            $indicator->save();

            $this->changeLogService->record($indicator, 'actualizado', $actor, $before, $indicator->fresh()->toArray(), 'Indicador PME actualizado.');

            return $indicator->fresh(['objective', 'strategy', 'responsibleUser']);
        });
    }

    public function storeMeasurement(PmeIndicator $indicator, array $payload, User $actor): PmeIndicatorMeasurement
    {
        return DB::transaction(function () use ($indicator, $payload, $actor) {
            $percentage = $this->compliance($payload['measured_value'], $indicator->target_value);
            $state = $payload['state'] ?? $this->measurementState($payload['measured_value'], $indicator->target_value);

            $measurement = PmeIndicatorMeasurement::query()->create([
                'pme_indicator_id' => $indicator->id,
                'measured_at' => $payload['measured_at'],
                'measured_value' => $payload['measured_value'],
                'compliance_percentage' => $percentage,
                'state' => $state,
                'information_source' => $payload['information_source'] ?? null,
                'analysis' => $payload['analysis'] ?? null,
                'observations' => $payload['observations'] ?? null,
                'responsible_user_id' => $payload['responsible_user_id'] ?? $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $before = $indicator->toArray();
            $indicator->update([
                'current_value' => $payload['measured_value'],
                'compliance_percentage' => $percentage,
                'state' => $state,
                'updated_by' => $actor->id,
            ]);

            $this->changeLogService->record($indicator, 'medicion_registrada', $actor, $before, $indicator->fresh()->toArray(), 'Medición histórica del indicador.');

            return $measurement->fresh(['indicator', 'responsibleUser']);
        });
    }

    private function compliance(float|int|string|null $current, float|int|string|null $target): float
    {
        $targetValue = (float) ($target ?? 0);
        $currentValue = (float) ($current ?? 0);

        if ($targetValue <= 0) {
            return 0;
        }

        return round(min(100, ($currentValue / $targetValue) * 100), 2);
    }

    private function measurementState(float|int|string|null $current, float|int|string|null $target): string
    {
        $targetValue = (float) ($target ?? 0);
        $currentValue = (float) ($current ?? 0);

        if ($targetValue <= 0 || $currentValue <= 0) {
            return 'sin_medicion';
        }

        if ($currentValue >= $targetValue) {
            return 'cumplido';
        }

        if ($currentValue >= ($targetValue * 0.75)) {
            return 'parcialmente_cumplido';
        }

        if ($currentValue >= ($targetValue * 0.5)) {
            return 'en_avance';
        }

        return 'critico';
    }
}
