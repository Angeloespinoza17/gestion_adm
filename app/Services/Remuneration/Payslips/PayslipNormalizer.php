<?php

namespace App\Services\Remuneration\Payslips;

use Illuminate\Support\Str;

class PayslipNormalizer
{
    public function label(?string $value): string
    {
        return (string) Str::of((string) $value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9%]+/', ' ')
            ->squish();
    }

    public function rut(?string $value): string
    {
        return strtoupper((string) preg_replace('/[^0-9Kk]/', '', (string) $value));
    }

    public function validRut(?string $value): bool
    {
        $rut = $this->rut($value);
        if (strlen($rut) < 2 || strlen($rut) > 10) {
            return false;
        }

        $body = substr($rut, 0, -1);
        $provided = substr($rut, -1);
        if (! ctype_digit($body)) {
            return false;
        }

        $sum = 0;
        $factor = 2;
        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += ((int) $body[$index]) * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $result = 11 - ($sum % 11);
        $expected = $result === 11 ? '0' : ($result === 10 ? 'K' : (string) $result);

        return $provided === $expected;
    }

    public function money(mixed $value): int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return 0;
        }

        $negative = str_contains($text, '-') || (str_starts_with($text, '(') && str_ends_with($text, ')'));
        $digits = preg_replace('/[^0-9]/', '', $text) ?: '0';
        $amount = (int) $digits;

        return $negative ? -$amount : $amount;
    }

    public function conceptCode(?string $label): ?string
    {
        return preg_match('/^\s*\((\d{4})\)/', (string) $label, $matches)
            ? strtoupper($matches[1])
            : null;
    }

    public function conceptDescription(?string $label): string
    {
        return trim((string) preg_replace('/^\s*\(\d{4}\)\s*/', '', (string) $label));
    }
}
