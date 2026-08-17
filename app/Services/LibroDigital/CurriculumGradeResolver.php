<?php

namespace App\Services\LibroDigital;

use Illuminate\Support\Str;

class CurriculumGradeResolver
{
    public function fromEducationLevel(mixed $level): ?string
    {
        if (! $level) {
            return null;
        }

        $type = mb_strtolower((string) $level->type);
        $plain = Str::of((string) $level->name)
            ->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

        if ($type === 'parvularia') {
            if (preg_match('/\b(nt\s*1|nivel transicion 1|pre\s*kinder|prekinder)\b/', $plain)) {
                return 'NT1';
            }
            if (preg_match('/\b(nt\s*2|nivel transicion 2|kinder)\b/', $plain)) {
                return 'NT2';
            }
        }
        if (preg_match('/\b([1-8])\b.*\bbasic/', $plain, $match)) {
            return $match[1].'B';
        }
        if (preg_match('/\b([1-4])\b.*\bmedi/', $plain, $match)) {
            return $match[1].'M';
        }

        $words = [
            'primer' => '1', 'primero' => '1', 'segundo' => '2', 'tercer' => '3', 'tercero' => '3',
            'cuarto' => '4', 'quinto' => '5', 'sexto' => '6', 'septimo' => '7', 'octavo' => '8',
        ];
        foreach ($words as $word => $number) {
            if (str_contains($plain, $word) && str_contains($plain, 'basic')) {
                return $number.'B';
            }
            if ((int) $number <= 4 && str_contains($plain, $word) && str_contains($plain, 'medi')) {
                return $number.'M';
            }
        }

        return null;
    }

    public function levelForGrade(string $grade): string
    {
        return match (true) {
            in_array($grade, ['NT1', 'NT2'], true) => 'PARVULARIA',
            str_ends_with($grade, 'B') => 'BASICA',
            default => 'MEDIA',
        };
    }
}
