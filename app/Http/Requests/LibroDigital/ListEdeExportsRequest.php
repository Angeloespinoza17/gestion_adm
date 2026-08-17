<?php

namespace App\Http\Requests\LibroDigital;

use App\Enums\LibroDigital\EdeExportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEdeExportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && collect([
            'libro_digital.ede.manage',
            'libro_digital.ede.export',
            'libro_digital.ede.validate',
            'libro_digital.ede.download',
        ])->contains(fn (string $permission): bool => $user->hasPermission($permission));
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'book_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_books,id'],
            'normative_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_regulatory_profiles,id'],
            'ede_version_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_ede_versions,id'],
            'status' => ['sometimes', 'nullable', Rule::in(array_column(EdeExportStatus::cases(), 'value'))],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
