<?php

namespace App\Http\Requests\Porter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePorterKeyGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $code = is_null($code) ? null : trim((string) $code);

        $this->merge([
            'code' => $code === '' ? null : $code,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:80', Rule::unique('porter_key_groups', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
