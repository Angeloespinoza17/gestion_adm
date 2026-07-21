<?php

namespace App\Http\Requests\Spaces;

use App\Models\MaintenanceDependency;
use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('active')) {
            $data['active'] = filter_var($this->input('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('requires_approval')) {
            $data['requires_approval'] = filter_var($this->input('requires_approval'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        foreach (['is_reservable', 'is_inventory_auditable', 'is_maintenance_location'] as $field) {
            if ($this->has($field)) {
                $data[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $dependencyId = $this->route('maintenanceDependency')?->id;
        $staffUserExists = Rule::exists('users', 'id')->where(function ($query) {
            $query
                ->where('active', true)
                ->where('user_type', 'staff')
                ->whereNotNull('staff_id')
                ->whereIn('staff_id', Staff::query()->where('active', true)->select('id'));
        });

        return [
            'dependency_type_id' => ['nullable', 'integer', 'exists:dependency_types,id'],
            'parent_dependency_id' => [
                'nullable',
                'integer',
                Rule::exists('maintenance_dependencies', 'id')
                    ->where('dependency_kind', MaintenanceDependency::KIND_SPACE)
                    ->where('active', true),
            ],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('maintenance_dependencies', 'code')->ignore($dependencyId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'floor_sector' => ['nullable', 'string', 'max:255'],
            'capacity_max' => ['nullable', 'integer', 'min:0'],
            'available_equipment' => ['nullable', 'string'],
            'availability_status' => ['sometimes', Rule::in([
                MaintenanceDependency::AVAILABILITY_AVAILABLE,
                MaintenanceDependency::AVAILABILITY_UNAVAILABLE,
                MaintenanceDependency::AVAILABILITY_MAINTENANCE,
                MaintenanceDependency::AVAILABILITY_BLOCKED,
            ])],
            'distribution' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'usage' => ['nullable', 'string', 'max:255'],
            'distribution_code' => ['nullable', 'string', 'max:50'],
            'floor_code' => ['nullable', 'string', 'max:50'],
            'dependency_code' => ['nullable', 'string', 'max:50'],
            'numbering' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'notes' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'calendar_color' => ['nullable', 'string', 'max:20'],
            'is_reservable' => ['sometimes', 'boolean'],
            'is_inventory_auditable' => ['sometimes', 'boolean'],
            'is_maintenance_location' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'approver_user_ids' => ['nullable', 'array'],
            'approver_user_ids.*' => ['integer', $staffUserExists],
            'image' => ['nullable', 'file', 'image', 'max:10240'],
        ];
    }
}
