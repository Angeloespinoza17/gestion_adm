<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeStudentSepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'classification' => ['required', Rule::in(PmeCatalogService::STUDENT_CLASSIFICATIONS)],
            'loaded_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:191'],
            'state' => ['required', Rule::in(PmeCatalogService::STUDENT_CLASSIFICATION_STATES)],
            'observations' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
