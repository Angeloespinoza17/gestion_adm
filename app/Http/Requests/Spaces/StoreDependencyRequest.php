<?php

namespace App\Http\Requests\Spaces;

use App\Models\MaintenanceDependency;
use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->has('active')
                ? filter_var($this->input('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true,
            'is_reservable' => $this->has('is_reservable')
                ? filter_var($this->input('is_reservable'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : false,
            'is_inventory_auditable' => $this->has('is_inventory_auditable')
                ? filter_var($this->input('is_inventory_auditable'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true,
            'is_maintenance_location' => $this->has('is_maintenance_location')
                ? filter_var($this->input('is_maintenance_location'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true,
            'requires_approval' => $this->has('requires_approval')
                ? filter_var($this->input('requires_approval'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true,
            'availability_status' => $this->input('availability_status') ?: MaintenanceDependency::AVAILABILITY_AVAILABLE,
        ]);
    }

    public function rules(): array
    {
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
            'code' => ['required', 'string', 'max:50', 'unique:maintenance_dependencies,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'floor_sector' => ['nullable', 'string', 'max:255'],
            'capacity_max' => ['nullable', 'integer', 'min:0'],
            'available_equipment' => ['nullable', 'string'],
            'availability_status' => ['required', Rule::in([
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
