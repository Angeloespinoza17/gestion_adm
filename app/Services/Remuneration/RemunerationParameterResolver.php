<?php

namespace App\Services\Remuneration;

use App\Models\Remuneration\RemunerationLegalParameter;
use App\Models\Remuneration\RemunerationPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RemunerationParameterResolver
{
    /**
     * @return Collection<string, RemunerationLegalParameter>
     */
    public function resolveForPeriod(RemunerationPeriod $period): Collection
    {
        return RemunerationLegalParameter::query()
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $period->period_end)
            ->where(function ($query) use ($period) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $period->period_start);
            })
            ->orderBy('code')
            ->orderByDesc('effective_from')
            ->get()
            ->unique('code')
            ->keyBy('code');
    }

    /**
     * @param  Collection<string, RemunerationLegalParameter>  $parameters
     */
    public function requiredValue(Collection $parameters, string $code): float
    {
        $parameter = $parameters->get($code);
        if (!$parameter) {
            throw ValidationException::withMessages([
                'parameters' => "Falta configurar el parámetro legal {$code} para el período.",
            ]);
        }

        return (float) $parameter->value;
    }

    /**
     * @param  Collection<string, RemunerationLegalParameter>  $parameters
     */
    public function optionalValue(Collection $parameters, string $code, float $default = 0): float
    {
        $parameter = $parameters->get($code);

        return $parameter ? (float) $parameter->value : $default;
    }

    /**
     * @param  Collection<string, RemunerationLegalParameter>  $parameters
     */
    public function requiredRate(Collection $parameters, string $code): float
    {
        return $this->normalizeRate($this->requiredValue($parameters, $code));
    }

    /**
     * @param  Collection<string, RemunerationLegalParameter>  $parameters
     */
    public function optionalRate(Collection $parameters, string $code, float $default = 0): float
    {
        return $this->normalizeRate($this->optionalValue($parameters, $code, $default));
    }

    /**
     * @param  Collection<string, RemunerationLegalParameter>  $parameters
     * @return array<string, float>
     */
    public function variables(Collection $parameters): array
    {
        $variables = [];
        foreach ($parameters as $code => $parameter) {
            $variables[$this->variableName($code)] = (float) $parameter->value;
        }

        return $variables;
    }

    /**
     * @param  Collection<string, RemunerationLegalParameter>  $parameters
     * @return array<string, mixed>
     */
    public function snapshot(Collection $parameters): array
    {
        return $parameters
            ->map(fn (RemunerationLegalParameter $parameter) => [
                'id' => $parameter->id,
                'code' => $parameter->code,
                'name' => $parameter->name,
                'category' => $parameter->category,
                'value' => (float) $parameter->value,
                'unit' => $parameter->unit,
                'effective_from' => optional($parameter->effective_from)->format('Y-m-d'),
                'effective_until' => optional($parameter->effective_until)->format('Y-m-d'),
                'source_reference' => $parameter->source_reference,
            ])
            ->values()
            ->all();
    }

    public function normalizeRate(float|int|string|null $value): float
    {
        $value = (float) ($value ?? 0);

        return $value > 1 ? $value / 100 : $value;
    }

    private function variableName(string $code): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $code) ?: $code;
    }
}
