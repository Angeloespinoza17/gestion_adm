<?php

namespace App\Http\Requests\PedagogicalManagement;

use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClassPresentationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('class-presentations.view') === true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'integer'], 'academic_year_id' => ['sometimes', 'integer'],
            'course_id' => ['sometimes', 'integer'], 'subject_id' => ['sometimes', 'integer'],
            'unit_id' => ['sometimes', 'integer'], 'user_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::enum(ClassPresentationStatus::class)],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2200'],
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
