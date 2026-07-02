<?php

namespace App\Http\Requests\Students;

use App\Models\EducationLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEducationLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->exists('name')) {
            $data['name'] = trim((string) $this->input('name'));
        }

        if ($this->exists('order')) {
            $data['order'] = $this->input('order') !== null && $this->input('order') !== ''
                ? (int) $this->input('order')
                : null;
        }

        if ($this->exists('type')) {
            $data['type'] = trim((string) $this->input('type'));
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $educationLevelId = $this->route('educationLevel')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('education_levels', 'name')->ignore($educationLevelId)],
            'order' => ['sometimes', 'required', 'integer', 'min:1', 'max:999', Rule::unique('education_levels', 'order')->ignore($educationLevelId)],
            'type' => ['sometimes', 'required', 'string', Rule::in(collect(EducationLevel::TYPE_OPTIONS)->pluck('value')->all())],
        ];
    }
}
