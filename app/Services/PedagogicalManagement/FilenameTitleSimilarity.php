<?php

namespace App\Services\PedagogicalManagement;

class FilenameTitleSimilarity
{
    public function __construct(private readonly InstrumentTextNormalizer $normalizer) {}

    /** @return array{score:float,filename_key:string,title_key:string,method:string} */
    public function compare(string $filename, string $title): array
    {
        $filenameKey = $this->normalizer->filenameKey($filename);
        $titleKey = $this->normalizer->searchKey($title);
        $filenameTokens = $this->tokens($filenameKey);
        $titleTokens = $this->tokens($titleKey);
        $union = array_values(array_unique([...$filenameTokens, ...$titleTokens]));
        $intersection = array_intersect($filenameTokens, $titleTokens);
        $jaccard = $union === [] ? 0.0 : count($intersection) / count($union);
        $maxLength = max(mb_strlen($filenameKey), mb_strlen($titleKey), 1);
        $edit = 1 - min(levenshtein(mb_substr($filenameKey, 0, 250), mb_substr($titleKey, 0, 250)) / $maxLength, 1);

        return [
            'score' => round(($jaccard * 0.7) + ($edit * 0.3), 4),
            'filename_key' => $filenameKey,
            'title_key' => $titleKey,
            'method' => 'jaccard_tokens_70_levenshtein_30',
        ];
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $ignored = ['pdf', 'docx', 'prueba', 'control', 'evaluacion', 'instrumento', 'forma', 'fila'];

        return collect(explode(' ', $value))
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, $ignored, true))
            ->unique()->values()->all();
    }
}
