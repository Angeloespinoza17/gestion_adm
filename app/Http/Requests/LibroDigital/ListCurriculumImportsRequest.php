<?php

namespace App\Http\Requests\LibroDigital;

use App\Models\LibroDigital\CurriculumImportBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCurriculumImportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && collect([
            'libro_digital.curriculum.import',
            'libro_digital.curriculum.approve',
            'libro_digital.curriculum.activate',
            'libro_digital.audit.view',
        ])->contains(fn (string $permission): bool => $user->hasPermission($permission));
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'status' => ['sometimes', 'nullable', Rule::in(CurriculumImportBatch::STATUSES)],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
