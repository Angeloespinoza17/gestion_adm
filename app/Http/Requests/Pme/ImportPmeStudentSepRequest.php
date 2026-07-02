<?php

namespace App\Http\Requests\Pme;

use Illuminate\Foundation\Http\FormRequest;

class ImportPmeStudentSepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'source' => ['nullable', 'string', 'max:191'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ];
    }
}
