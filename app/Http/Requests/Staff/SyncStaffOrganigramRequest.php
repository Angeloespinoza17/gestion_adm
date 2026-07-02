<?php

namespace App\Http\Requests\Staff;

use App\Models\StaffOrganigramRelation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncStaffOrganigramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'relations' => ['nullable', 'array'],
            'relations.*.relationship_type' => ['required', Rule::in(array_column(StaffOrganigramRelation::RELATIONSHIP_OPTIONS, 'value'))],
            'relations.*.related_staff_id' => ['required', 'integer', 'exists:staff,id'],
            'relations.*.custom_label' => ['nullable', 'string', 'max:255'],
            'relations.*.priority' => ['nullable', 'integer', 'min:1', 'max:999'],
            'relations.*.is_primary' => ['sometimes', 'boolean'],
            'relations.*.active' => ['sometimes', 'boolean'],
            'relations.*.notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $staffId = (int) $this->route('staff')?->id;
            $relations = $this->input('relations', []);

            foreach ($relations as $index => $relation) {
                $relationshipType = $relation['relationship_type'] ?? null;
                $relatedStaffId = (int) ($relation['related_staff_id'] ?? 0);
                $customLabel = trim((string) ($relation['custom_label'] ?? ''));

                if ($staffId > 0 && $relatedStaffId === $staffId) {
                    $validator->errors()->add("relations.$index.related_staff_id", 'Un funcionario no puede relacionarse consigo mismo.');
                }

                if ($relationshipType === 'other' && $customLabel === '') {
                    $validator->errors()->add("relations.$index.custom_label", 'Debes indicar el nombre de la relación.');
                }
            }
        });
    }
}
