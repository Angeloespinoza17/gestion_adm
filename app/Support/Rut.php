<?php

namespace App\Support;

class Rut
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = strtoupper(trim($value));
        $clean = preg_replace('/[^0-9K]/', '', $clean ?? '');

        if (!$clean || strlen($clean) < 2) {
            return null;
        }

        $body = substr($clean, 0, -1);
        $dv = substr($clean, -1);

        if ($body === '' || !ctype_digit($body)) {
            return null;
        }

        return ltrim($body, '0') . '-' . $dv;
    }

    public static function isValid(?string $value): bool
    {
        $normalized = static::normalize($value);
        if (!$normalized) {
            return false;
        }

        [$body, $dv] = explode('-', $normalized);

        if (!ctype_digit($body)) {
            return false;
        }

        $sum = 0;
        $multiplier = 2;

        foreach (array_reverse(str_split($body)) as $digit) {
            $sum += ((int) $digit) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $remainder = 11 - ($sum % 11);
        $expected = match ($remainder) {
            11 => '0',
            10 => 'K',
            default => (string) $remainder,
        };

        return $expected === $dv;
    }
}
