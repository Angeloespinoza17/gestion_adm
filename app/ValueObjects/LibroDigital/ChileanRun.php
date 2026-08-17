<?php

namespace App\ValueObjects\LibroDigital;

use InvalidArgumentException;

final readonly class ChileanRun
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = self::normalize($value);

        if (! self::isValid($normalized)) {
            throw new InvalidArgumentException('El RUN no tiene un formato o digito verificador valido.');
        }

        $this->value = $normalized;
    }

    public static function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[^0-9Kk]/', '', trim($value)));
    }

    public static function isValid(string $value): bool
    {
        $normalized = self::normalize($value);
        if (! preg_match('/^[0-9]{7,8}[0-9K]$/', $normalized)) {
            return false;
        }

        $body = substr($normalized, 0, -1);
        $expected = substr($normalized, -1);
        $factor = 2;
        $sum = 0;

        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += ((int) $body[$index]) * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $remainder = 11 - ($sum % 11);
        $calculated = match ($remainder) {
            11 => '0',
            10 => 'K',
            default => (string) $remainder,
        };

        return hash_equals($calculated, $expected);
    }

    public function formatted(): string
    {
        $body = substr($this->value, 0, -1);
        $dv = substr($this->value, -1);

        return number_format((int) $body, 0, ',', '.').'-'.$dv;
    }

    public function masked(): string
    {
        return '***.***.'.substr($this->value, -3, 2).'-'.substr($this->value, -1);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
