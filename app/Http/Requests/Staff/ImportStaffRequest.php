<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ImportStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'update_existing' => $this->has('update_existing')
                ? filter_var($this->input('update_existing'), FILTER_VALIDATE_BOOLEAN)
                : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt,zip', 'max:10240'],
            'update_existing' => ['required', 'boolean'],
        ];
    }
}
