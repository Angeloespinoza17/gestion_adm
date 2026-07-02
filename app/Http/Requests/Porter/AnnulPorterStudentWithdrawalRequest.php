<?php

namespace App\Http\Requests\Porter;

use Illuminate\Foundation\Http\FormRequest;

class AnnulPorterStudentWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string'],
        ];
    }
}
