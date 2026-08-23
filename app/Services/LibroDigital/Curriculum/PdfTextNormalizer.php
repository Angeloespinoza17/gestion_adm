<?php

namespace App\Services\LibroDigital\Curriculum;

use Illuminate\Support\Str;

class PdfTextNormalizer
{
    public function normalize(string $text): string
    {
        // Algunos generadores PDF dejan bytes inválidos aislados en la capa de
        // texto. Se reemplazan antes de aplicar regex Unicode para no perder una
        // página completa por PREG_BAD_UTF8_ERROR.
        $text = mb_scrub($text, 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/(?<=\pL)-\s*\n\s*(?=\pL)/u', '', $text) ?? $text;
        $lines = collect(explode("\n", $text))
            ->map(fn (string $line): string => trim(preg_replace('/[\t ]+/u', ' ', $line) ?? $line))
            ->filter(fn (string $line): bool => $line !== '');

        return trim($lines->implode("\n"));
    }

    public function key(string $text): string
    {
        return Str::of($text)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    public function printedPageLabel(string $text): ?string
    {
        $head = array_slice(explode("\n", $text), 0, 8);
        foreach ($head as $line) {
            if (preg_match('/^\s*(\d{1,3}|[ivxlcdm]{1,8})\s*$/iu', trim($line), $match) === 1) {
                return $match[1];
            }
        }

        $header = implode(' ', array_slice($head, 0, 3));
        if (preg_match('/\b(\d{1,3})\s*Unidad\s+\d{1,2}\b/iu', $header, $match) === 1) {
            return $match[1];
        }
        foreach ($head as $line) {
            if (preg_match('/(?:b[aá]sico|medio|Programa de Estudio)[^\n]*?\b(\d{1,3})\D*$/iu', $line, $match) === 1) {
                return $match[1];
            }
        }

        if (preg_match('/^\s*(\d{1,3})\s+(?:Programa de Estudio|[\pL ]+Unidad\s+\d)/iu', $text, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
