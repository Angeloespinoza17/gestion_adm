<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAmendmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.amendments.request') ?? false;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', 'exists:lcd_books,id'],
            'entity_type' => ['required', Rule::in([
                'session', 'session_attendance', 'assessment', 'student_result', 'coexistence_entry', 'pie_support_record',
                'absence_case', 'parvularia_plan', 'parvularia_evaluation',
            ])],
            'entity_id' => ['required'],
            'original_revision' => ['required', 'integer', 'min:1'],
            'section' => ['sometimes', 'nullable', 'string', 'max:100'],
            'field' => ['sometimes', 'nullable', 'string', 'max:120'],
            'proposed' => ['required', 'array', 'min:1', 'max:8'],
            'reason' => ['required', 'string', 'min:10', 'max:3000'],
        ];
    }
}
