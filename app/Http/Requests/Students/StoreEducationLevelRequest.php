<?php

namespace App\Http\Requests\Students;

use App\Models\EducationLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEducationLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'order' => $this->input('order') !== null && $this->input('order') !== ''
                ? (int) $this->input('order')
                : null,
            'type' => trim((string) $this->input('type')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', 'unique:education_levels,name'],
            'order' => ['required', 'integer', 'min:1', 'max:999', 'unique:education_levels,order'],
            'type' => ['required', 'string', Rule::in(collect(EducationLevel::TYPE_OPTIONS)->pluck('value')->all())],
        ];
    }
}
