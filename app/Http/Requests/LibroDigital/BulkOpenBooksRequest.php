<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class BulkOpenBooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.books.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'allow_unassigned_teacher' => ['sometimes', 'boolean'],
            'books' => ['required', 'array', 'min:1', 'max:200'],
            'books.*.id' => ['required', 'integer', 'distinct'],
            'books.*.lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
