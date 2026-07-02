<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaProtocolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol_type_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'criticality_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'name' => ['required', 'string', 'max:191'],
            'type_label' => ['nullable', 'string', 'max:160'],
            'criticality_label' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'required_documents' => ['nullable', 'string'],
            'safeguard_measures' => ['nullable', 'string'],
            'minimal_actions' => ['nullable', 'string'],
            'default_due_days' => ['nullable', 'integer', 'min:1', 'max:180'],
            'status' => ['required', Rule::in(array_column(ConvivenciaProtocol::STATUS_OPTIONS, 'value'))],
            'is_sensitive' => ['sometimes', 'boolean'],
            'steps' => ['nullable', 'array'],
            'steps.*.step_order' => ['nullable', 'integer', 'min:1'],
            'steps.*.stage_name' => ['required_with:steps', 'string', 'max:160'],
            'steps.*.responsible_label' => ['nullable', 'string', 'max:160'],
            'steps.*.due_days' => ['nullable', 'integer', 'min:1', 'max:180'],
            'steps.*.required_documents' => ['nullable', 'string'],
            'steps.*.minimal_actions' => ['nullable', 'string'],
            'steps.*.safeguard_measures' => ['nullable', 'string'],
        ];
    }
}
