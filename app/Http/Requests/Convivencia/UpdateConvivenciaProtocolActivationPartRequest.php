<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaProtocolActivationPart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConvivenciaProtocolActivationPartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(ConvivenciaProtocolActivationPart::STATUS_OPTIONS)],
            'notes' => ['nullable', 'string'],
            'evidence_summary' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'started_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
