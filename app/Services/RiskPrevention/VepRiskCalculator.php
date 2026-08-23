<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\RiskCatalogItem;
use App\Models\RiskPrevention\RiskMethodology;
use Illuminate\Validation\ValidationException;

class VepRiskCalculator
{
    /** @return array<string, mixed> */
    public function calculate(int $probability, int $consequence, ?RiskMethodology $methodology = null): array
    {
        if (! in_array($probability, [1, 2, 4], true)) {
            throw ValidationException::withMessages(['probability' => 'La probabilidad debe ser un entero: 1, 2 o 4.']);
        }
        if (! in_array($consequence, [1, 2, 4], true)) {
            throw ValidationException::withMessages(['consequence' => 'La consecuencia debe ser un entero: 1, 2 o 4.']);
        }

        $score = $probability * $consequence;
        $level = $this->levelFor($score, $methodology);
        $configuration = $level['configuration'];

        return [
            'methodology_id' => $methodology?->id,
            'methodology_code' => $methodology?->code ?? 'ISP-2025-VEP',
            'methodology_version' => $methodology?->version_number ?? 1,
            'probability_score' => $probability,
            'probability_label' => [1 => 'Baja', 2 => 'Media', 4 => 'Alta'][$probability],
            'consequence_score' => $consequence,
            'consequence_label' => [1 => 'Ligeramente dañina / baja', 2 => 'Dañina / media', 4 => 'Extremadamente dañina / alta'][$consequence],
            'vep' => $score,
            'risk_level_id' => $level['id'],
            'risk_level_code' => $level['code'],
            'risk_level_label' => $level['name'],
            'risk_level_color' => $level['color'],
            'recommended_action' => $configuration['recommended_action'],
            'requires_action_plan' => (bool) $configuration['requires_action_plan'],
            'blocks_approval' => (bool) $configuration['blocks_approval'],
            'severe_consequence_warning' => $consequence === 4,
        ];
    }

    /** @return array<string, mixed> */
    private function levelFor(int $score, ?RiskMethodology $methodology): array
    {
        if ($methodology?->exists) {
            $item = RiskCatalogItem::query()
                ->where('methodology_id', $methodology->id)
                ->type('risk_level')
                ->current()
                ->orderBy('sort_order')
                ->get()
                ->first(fn (RiskCatalogItem $level) => in_array($score, $level->configuration['scores'] ?? [], true));

            if ($item) {
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'color' => $item->color,
                    'configuration' => $item->configuration,
                ];
            }
        }

        return match ($score) {
            1, 2 => $this->fallback('tolerable', 'Tolerable', '#2e7d32', false, false, 'Mantener controles y verificar periódicamente.'),
            4 => $this->fallback('moderate', 'Moderado', '#d97706', true, false, 'Planificar medidas de reducción y verificar su eficacia.'),
            8 => $this->fallback('important', 'Importante', '#dc6803', true, false, 'Implementar medidas, responsable y plazo antes de aprobar.'),
            16 => $this->fallback('intolerable', 'Intolerable', '#b42318', true, true, 'Suspender, eliminar o reducir inmediatamente la exposición.'),
        };
    }

    /** @return array<string, mixed> */
    private function fallback(string $code, string $name, string $color, bool $action, bool $block, string $recommendation): array
    {
        return [
            'id' => null,
            'code' => $code,
            'name' => $name,
            'color' => $color,
            'configuration' => [
                'requires_action_plan' => $action,
                'blocks_approval' => $block,
                'recommended_action' => $recommendation,
            ],
        ];
    }
}
