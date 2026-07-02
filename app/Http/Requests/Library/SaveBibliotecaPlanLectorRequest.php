<?php

namespace App\Http\Requests\Library;

use App\Models\Library\BibliotecaPlanLector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBibliotecaPlanLectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
            'subject' => ['required', 'string', 'max:120'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'biblioteca_obra_id' => ['required', 'integer', 'exists:biblioteca_obras,id'],
            'period' => ['nullable', 'string', 'max:80'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'objective' => ['nullable', 'string'],
            'associated_activity' => ['nullable', 'string'],
            'evaluation_description' => ['nullable', 'string'],
            'required_copies' => ['required', 'integer', 'min:1'],
            'fulfillment_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(BibliotecaPlanLector::STATUS_OPTIONS)],
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'max:2048'],
        ];
    }
}
