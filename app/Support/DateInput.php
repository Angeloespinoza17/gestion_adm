<?php

namespace App\Support;

use Carbon\Carbon;

class DateInput
{
    public static function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }
}
