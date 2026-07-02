<?php

namespace App\Http\Requests\Porter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolvePorterStudentWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['autorizado', 'observado', 'rechazado'])],
            'reason' => ['required', 'string'],
        ];
    }
}
