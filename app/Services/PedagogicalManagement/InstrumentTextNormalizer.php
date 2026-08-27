<?php

namespace App\Services\PedagogicalManagement;

use Illuminate\Support\Str;
use Normalizer;

class InstrumentTextNormalizer
{
    public function normalize(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        }
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/(?<=\pL)-\s*\n\s*(?=\pL)/u', '', $text) ?? $text;
        $lines = array_map(
            fn (string $line): string => trim(preg_replace('/[\t ]+/u', ' ', $line) ?? $line),
            explode("\n", $text),
        );

        return trim(implode("\n", $lines));
    }

    public function searchKey(string $text): string
    {
        return Str::of($this->normalize($text))
            ->lower()->ascii()->replaceMatches('/[^a-z0-9%]+/', ' ')->squish()->toString();
    }

    public function normalizedOaCode(int|string $number): string
    {
        return 'OA '.(int) $number;
    }

    public function filenameKey(string $filename): string
    {
        $withoutExtension = preg_replace('/\.[^.]+$/u', '', basename($filename)) ?? $filename;

        return $this->searchKey($withoutExtension);
    }
}
