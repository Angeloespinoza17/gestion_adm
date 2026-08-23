<?php

namespace App\Services\RiskPrevention;

use Illuminate\Support\Str;

class LegacyRiskValueNormalizer
{
    public function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = preg_replace('/\s+/u', ' ', str_replace(["\r", "\n", "\t"], ' ', trim((string) $value)));

        return $text === '' ? null : $text;
    }

    public function key(mixed $value): string
    {
        return Str::of($this->text($value) ?? '')
            ->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    public function boolean(mixed $value): ?bool
    {
        $key = $this->key($value);
        if (in_array($key, ['si', '1', 'verdadero', 'true'], true)) {
            return true;
        }
        if (in_array($key, ['no', '0', 'falso', 'false'], true)) {
            return false;
        }

        return null;
    }

    /** @return array{value: ?int, warning: ?string} */
    public function vepFactor(mixed $value, string $kind): array
    {
        $text = $this->text($value);
        if ($text === null) {
            return ['value' => null, 'warning' => null];
        }
        preg_match('/(?:^|\()\s*([124])\s*(?:\)|$)/', $text, $match);
        $numeric = is_numeric($text) ? (int) $text : (isset($match[1]) ? (int) $match[1] : null);
        $labels = $kind === 'probability'
            ? ['baja' => 1, 'media' => 2, 'alta' => 4]
            : ['baja' => 1, 'ligeramente danina' => 1, 'media' => 2, 'danina' => 2, 'alta' => 4, 'extremadamente danina' => 4];
        $key = $this->key($text);
        $labelValue = collect($labels)->first(fn ($score, $label) => str_contains($key, $label));
        $value = $numeric ?? $labelValue;
        $warning = ($numeric !== null && $labelValue !== null && $numeric !== $labelValue)
            ? "El número {$numeric} no coincide con la etiqueta importada; se usó el número como fuente canónica."
            : null;

        return ['value' => $value, 'warning' => $warning];
    }

    public function routineType(mixed $value): string
    {
        $key = $this->key($value);
        if (str_contains($key, 'no rutin')) {
            return 'non_routine';
        }
        if (str_contains($key, 'emerg')) {
            return 'emergency';
        }
        if (str_contains($key, 'ocas')) {
            return 'occasional';
        }

        return 'routine';
    }

    public function hierarchy(mixed $value): string
    {
        $key = $this->key($value);

        return match (true) {
            str_contains($key, 'elimin') => 'elimination',
            str_contains($key, 'sustit') => 'substitution',
            str_contains($key, 'ingenier') => 'engineering',
            str_contains($key, 'administr') => 'administrative',
            str_contains($key, 'epp'), str_contains($key, 'proteccion personal') => 'personal_protective_equipment',
            default => 'administrative',
        };
    }

    public function periodicity(mixed $value): string
    {
        $key = $this->key($value);

        return match (true) {
            str_contains($key, 'cada vez'), str_contains($key, 'evento') => 'per_event',
            str_contains($key, 'diar') => 'daily',
            str_contains($key, 'seman') => 'weekly',
            str_contains($key, 'mens') => 'monthly',
            str_contains($key, 'trimes') => 'quarterly',
            str_contains($key, 'semes') => 'semiannual',
            str_contains($key, 'anual') => 'annual',
            blank($key), $key === 'una vez' => 'once',
            default => 'custom',
        };
    }

    public function classification(mixed $value): ?string
    {
        $key = $this->key($value);

        return match (true) {
            str_contains($key, 'intolerable') => 'intolerable',
            str_contains($key, 'importante') => 'important',
            str_contains($key, 'moderado') => 'moderate',
            str_contains($key, 'tolerable') => 'tolerable',
            default => null,
        };
    }
}
