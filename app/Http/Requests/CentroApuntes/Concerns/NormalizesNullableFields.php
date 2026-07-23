<?php

namespace App\Http\Requests\CentroApuntes\Concerns;

trait NormalizesNullableFields
{
    /**
     * @param  array<int, string>  $fields
     */
    protected function normalizeNullableFields(array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (!$this->exists($field)) {
                continue;
            }

            $value = $this->input($field);
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $normalized[$field] = null;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
