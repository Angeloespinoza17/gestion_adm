<?php

namespace App\Http\Requests\Porter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePorterKeyLoanReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['devuelta', 'observada'])],
            'return_observations' => ['nullable', 'string'],
        ];
    }
}
