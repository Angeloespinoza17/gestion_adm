<?php

namespace App\Http\Requests\Porter;

use App\Models\PorterVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePorterVisitStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['finalizada', 'rechazada'])],
            'observations' => ['nullable', 'string'],
        ];
    }
}
