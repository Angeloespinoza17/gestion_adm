<?php

namespace App\Http\Requests\Students;

use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawStudentEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'effective_date' => DateInput::normalize($this->input('effective_date')) ?: now()->format('Y-m-d'),
        ]);
    }

    public function rules(): array
    {
        return [
            'effective_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
