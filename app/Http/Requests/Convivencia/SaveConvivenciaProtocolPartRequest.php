<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaProtocolPart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveConvivenciaProtocolPartRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $part = $this->route('protocolPart');
        if ($this->filled('code')) {
            $this->merge(['code' => trim((string) $this->input('code'))]);
        } elseif ($part?->code) {
            $this->merge(['code' => $part->code]);
        } elseif ($this->filled('title')) {
            $this->merge(['code' => str_replace('-', '_', Str::slug((string) $this->input('title')))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $part = $this->route('protocolPart');

        return [
            'category' => ['required', Rule::in(ConvivenciaProtocolPart::CATEGORY_OPTIONS)],
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('convivencia_protocol_parts', 'code')->ignore($part?->id)],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'responsible_label' => ['nullable', 'string', 'max:160'],
            'population_scope' => ['nullable', 'string', 'max:120'],
            'legal_reference' => ['nullable', 'string'],
            'deadline_value' => ['nullable', 'integer', 'min:1', 'max:365'],
            'deadline_unit' => ['nullable', Rule::in(ConvivenciaProtocolPart::DEADLINE_UNITS)],
            'deadline_anchor' => ['nullable', Rule::in(['activation_started', 'step_started', 'previous_step_completed'])],
            'requires_evidence' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
