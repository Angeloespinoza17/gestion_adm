<?php

namespace App\Services\LibroDigital;

final class CurriculumObjectiveIdentity
{
    /**
     * El código oficial conserva su capitalización original. Los campos de
     * clasificación se normalizan para que backfill e importación coincidan.
     *
     * @param  array<string, mixed>  $scope
     */
    public static function key(array $scope): string
    {
        $identity = [
            'code' => self::text($scope['code'] ?? null),
            'objective_type' => self::upper($scope['objective_type'] ?? null),
            'subject_code' => self::upper($scope['subject_code'] ?? null),
            'level_code' => self::upper($scope['level_code'] ?? null),
            'grade_code' => self::upper($scope['grade_code'] ?? null),
            'curriculum_track' => self::upper($scope['curriculum_track'] ?? null),
            'axis_code' => self::upper($scope['axis_code'] ?? null),
        ];
        ksort($identity, SORT_STRING);

        return hash('sha256', json_encode(
            $identity,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    private static function upper(mixed $value): ?string
    {
        $text = self::text($value);

        return $text === null ? null : mb_strtoupper($text);
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
