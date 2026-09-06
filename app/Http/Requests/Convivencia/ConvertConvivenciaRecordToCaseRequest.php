<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class ConvertConvivenciaRecordToCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_type_item_id' => ['nullable', 'integer', $this->catalogItemRule('case_type')],
            'classification_item_id' => ['required', 'integer', $this->catalogItemRule('classification')],
            'subclassification_item_id' => ['nullable', 'integer', $this->catalogItemRule('subclassification')],
            'criticality_item_id' => ['required', 'integer', $this->catalogItemRule('criticality')],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'follow_up_due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'is_sensitive' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['classification_item_id', 'subclassification_item_id'])) {
                return;
            }

            $classificationId = $this->integer('classification_item_id');
            $subclassificationId = $this->integer('subclassification_item_id');
            if (! $classificationId || ! $subclassificationId) {
                return;
            }

            $belongsToClassification = ConvivenciaCatalogItem::query()
                ->whereKey($subclassificationId)
                ->where('group', 'subclassification')
                ->where('parent_id', $classificationId)
                ->exists();

            if (! $belongsToClassification) {
                $validator->errors()->add(
                    'subclassification_item_id',
                    'La subclasificación no pertenece a la clasificación seleccionada.',
                );
            }
        });
    }

    private function catalogItemRule(string $group): Exists
    {
        return Rule::exists('convivencia_catalog_items', 'id')
            ->where(fn ($query) => $query->where('group', $group)->where('active', true));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'classification_item_id.required' => 'Selecciona la clasificación del nuevo caso.',
            'criticality_item_id.required' => 'Selecciona la criticidad del nuevo caso.',
            'responsible_user_id.required' => 'Selecciona a la persona responsable del nuevo caso.',
            'follow_up_due_at.after_or_equal' => 'El seguimiento no puede quedar en una fecha anterior a hoy.',
        ];
    }
}
