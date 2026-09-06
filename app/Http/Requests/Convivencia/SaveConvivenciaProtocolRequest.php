<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaProtocolRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => trim((string) $this->input('code'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $protocol = $this->route('protocol');
        $revisionRules = $protocol
            ? ['required', 'integer', 'min:1']
            : ['nullable', 'integer', 'min:1'];

        return [
            'code' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('convivencia_protocols', 'code')->ignore($protocol?->id)],
            'expected_revision' => $revisionRules,
            'version_label' => ['nullable', 'string', 'max:80'],
            'regulatory_source' => ['nullable', 'string', 'max:191'],
            'education_scope' => ['nullable', 'string', 'max:120'],
            'legal_reference' => ['nullable', 'string'],
            'source_reference' => ['nullable', 'string'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'published_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
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
            'steps.*.id' => ['nullable', 'integer', 'exists:convivencia_protocol_steps,id'],
            'steps.*.step_order' => ['nullable', 'integer', 'min:1'],
            'steps.*.code' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'steps.*.stage_name' => ['required_with:steps', 'string', 'max:160'],
            'steps.*.description' => ['nullable', 'string'],
            'steps.*.step_type' => ['nullable', 'string', 'max:60'],
            'steps.*.responsible_label' => ['nullable', 'string', 'max:160'],
            'steps.*.due_days' => ['nullable', 'integer', 'min:1', 'max:180'],
            'steps.*.deadline_value' => ['nullable', 'integer', 'min:1', 'max:365'],
            'steps.*.deadline_unit' => ['nullable', Rule::in(['hours', 'calendar_days', 'business_days', 'school_days', 'external', 'external_defined'])],
            'steps.*.deadline_anchor' => ['nullable', Rule::in(['activation_started', 'step_started', 'previous_step_completed'])],
            'steps.*.can_extend' => ['sometimes', 'boolean'],
            'steps.*.extension_value' => ['nullable', 'integer', 'min:1', 'max:365'],
            'steps.*.extension_unit' => ['nullable', Rule::in(['hours', 'calendar_days', 'business_days', 'school_days', 'external', 'external_defined'])],
            'steps.*.completion_rule' => ['nullable', 'array'],
            'steps.*.active' => ['sometimes', 'boolean'],
            'steps.*.metadata' => ['nullable', 'array'],
            'steps.*.required_documents' => ['nullable', 'string'],
            'steps.*.minimal_actions' => ['nullable', 'string'],
            'steps.*.safeguard_measures' => ['nullable', 'string'],
            'steps.*.part_links' => ['nullable', 'array'],
            'steps.*.part_links.*.id' => ['nullable', 'integer', 'exists:convivencia_protocol_part_links,id'],
            'steps.*.part_links.*.protocol_part_id' => ['required', 'integer', 'exists:convivencia_protocol_parts,id'],
            'steps.*.part_links.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'steps.*.part_links.*.is_required' => ['sometimes', 'boolean'],
            'steps.*.part_links.*.condition' => ['nullable', 'array'],
            'steps.*.part_links.*.configuration' => ['nullable', 'array'],
            'steps.*.parts' => ['nullable', 'array'],
            'steps.*.parts.*.id' => ['nullable', 'integer', 'exists:convivencia_protocol_part_links,id'],
            'steps.*.parts.*.protocol_part_id' => ['required', 'integer', 'exists:convivencia_protocol_parts,id'],
            'steps.*.parts.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'steps.*.parts.*.is_required' => ['sometimes', 'boolean'],
            'steps.*.parts.*.condition' => ['nullable', 'array'],
            'steps.*.parts.*.configuration' => ['nullable', 'array'],
            'part_links' => ['nullable', 'array'],
            'part_links.*.id' => ['nullable', 'integer', 'exists:convivencia_protocol_part_links,id'],
            'part_links.*.protocol_step_id' => ['nullable', 'integer', 'exists:convivencia_protocol_steps,id'],
            'part_links.*.step_code' => ['nullable', 'string', 'max:80'],
            'part_links.*.protocol_part_id' => ['required', 'integer', 'exists:convivencia_protocol_parts,id'],
            'part_links.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'part_links.*.is_required' => ['sometimes', 'boolean'],
            'part_links.*.condition' => ['nullable', 'array'],
            'part_links.*.configuration' => ['nullable', 'array'],
            'parts' => ['nullable', 'array'],
            'parts.*.id' => ['nullable', 'integer', 'exists:convivencia_protocol_part_links,id'],
            'parts.*.protocol_step_id' => ['nullable', 'integer', 'exists:convivencia_protocol_steps,id'],
            'parts.*.step_code' => ['nullable', 'string', 'max:80'],
            'parts.*.protocol_part_id' => ['required', 'integer', 'exists:convivencia_protocol_parts,id'],
            'parts.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'parts.*.is_required' => ['sometimes', 'boolean'],
            'parts.*.condition' => ['nullable', 'array'],
            'parts.*.configuration' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $steps = collect($this->input('steps', []));
            $orders = $steps->pluck('step_order')
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => (int) $value);
            if ($orders->count() !== $orders->uniqueStrict()->count()) {
                $validator->errors()->add('steps', 'El orden de cada paso debe ser único dentro del protocolo.');
            }

            $codes = $steps->pluck('code')
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => mb_strtolower(trim((string) $value)));
            if ($codes->count() !== $codes->uniqueStrict()->count()) {
                $validator->errors()->add('steps', 'El código de cada paso debe ser único dentro del protocolo.');
            }

            foreach ($steps as $index => $step) {
                $links = collect($step['part_links'] ?? $step['parts'] ?? []);
                $partIds = $links->pluck('protocol_part_id')->filter()->map(fn ($value) => (int) $value);
                if ($partIds->count() !== $partIds->uniqueStrict()->count()) {
                    $validator->errors()->add("steps.{$index}.part_links", 'Una parte no puede repetirse dentro del mismo paso.');
                }
            }
        });
    }
}
