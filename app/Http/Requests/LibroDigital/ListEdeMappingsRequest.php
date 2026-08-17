<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEdeMappingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->hasAnyPermission([
            'libro_digital.ede.manage',
            'libro_digital.ede.export',
            'libro_digital.ede.validate',
            'libro_digital.ede.download',
        ]);
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'normative_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_regulatory_profiles,id'],
            'ede_version_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_ede_versions,id'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive', 'mapped', 'missing', 'blocked'])],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /** @param list<string> $permissions */
    private function hasAnyPermission(array $permissions): bool
    {
        $user = $this->user();

        return $user !== null && collect($permissions)->contains(
            fn (string $permission): bool => $user->hasPermission($permission)
        );
    }
}
