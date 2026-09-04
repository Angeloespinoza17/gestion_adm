<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use Illuminate\Support\Str;

class PresentationTitleSuggester
{
    /** @param list<array{code:string,description:string}> $objectives @return list<string> */
    public function suggest(string $unit, array $objectives, string $classType): array
    {
        $focus = trim((string) Str::of($objectives[0]['description'] ?? $unit)->before(':')->before(';'));
        $focus = Str::limit($focus, 72, '');
        $unitName = trim($unit) !== '' ? trim($unit) : 'Unidad de aprendizaje';
        $classLabel = config("class_presentations.options.class_type.{$classType}", 'Clase');

        return collect([
            "{$unitName}: {$focus}",
            "{$classLabel} · {$focus}",
            'Aprendemos a '.Str::lcfirst($focus),
        ])->map(fn (string $title): string => Str::limit(Str::squish($title), 180, ''))->unique()->values()->all();
    }
}
