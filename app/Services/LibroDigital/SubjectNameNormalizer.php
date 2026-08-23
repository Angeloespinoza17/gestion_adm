<?php

namespace App\Services\LibroDigital;

use Illuminate\Support\Str;

class SubjectNameNormalizer
{
    public function normalize(string $value): string
    {
        $value = preg_replace('/^\s*\(\*{1,2}\)\s*/u', '', $value) ?? $value;

        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))) ?? '');
    }

    public function mappingKey(string $scopeCode, string $externalName): string
    {
        return hash('sha256', Str::lower(trim($scopeCode)).'|'.$this->normalize($externalName));
    }

    /** @return list<string> */
    public function candidateKeys(string $externalName): array
    {
        $keys = [$this->normalize($externalName)];
        if (str_contains($externalName, ':')) {
            $keys[] = $this->normalize(Str::afterLast($externalName, ':'));
        }

        return array_values(array_unique(array_filter($keys)));
    }
}
