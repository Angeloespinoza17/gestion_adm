<?php

namespace App\Http\Requests\Staff;

use App\Models\Commune;
use App\Models\Staff;
use App\Models\User;
use App\Support\DateInput;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        $departmentIds = $this->input('department_ids');

        if ($this->has('rut')) {
            $data['rut'] = Rut::normalize($this->input('rut'));
        }

        if ($this->has('institutional_email')) {
            $institutionalEmail = $this->input('institutional_email');
            $data['institutional_email'] = $institutionalEmail ? mb_strtolower(trim((string) $institutionalEmail)) : null;
        }

        if ($this->has('personal_email')) {
            $personalEmail = $this->input('personal_email');
            $data['personal_email'] = $personalEmail ? mb_strtolower(trim((string) $personalEmail)) : null;
        }

        foreach (['birth_date', 'start_date', 'end_date'] as $dateField) {
            if ($this->exists($dateField)) {
                $data[$dateField] = DateInput::normalize($this->input($dateField));
            }
        }

        if ($this->exists('active')) {
            $data['active'] = filter_var($this->input('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($this->exists('can_receive_maintenance_orders')) {
            $data['can_receive_maintenance_orders'] = filter_var(
                $this->input('can_receive_maintenance_orders'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
        }

        if ($this->exists('maintenance_role')) {
            $data['maintenance_role'] = $this->input('maintenance_role') ?: null;
        }

        if (is_string($departmentIds)) {
            $decoded = json_decode($departmentIds, true);
            $data['department_ids'] = is_array($decoded) ? $decoded : [];
        }

        if (($this->input('contract_type') ?: null) === 'indefinido') {
            $data['end_date'] = null;
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $routeStaff = $this->route('staff');
        $staffId = $routeStaff instanceof Staff ? $routeStaff->id : $routeStaff;

        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'rut' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('staff', 'rut')->ignore($staffId),
            ],
            'birth_date' => ['nullable', 'date'],
            'institutional_email' => ['nullable', 'email', 'max:255', Rule::unique('staff', 'institutional_email')->ignore($staffId)],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'commune_id' => [
                'nullable',
                'integer',
                'exists:communes,id',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

                    $commune = Commune::query()->find($value);
                    $regionId = $this->input('region_id');

                    if (!$regionId) {
                        $fail('Selecciona una región para la comuna indicada.');
                        return;
                    }

                    if ($commune && (int) $commune->region_id !== (int) $regionId) {
                        $fail('La comuna seleccionada no pertenece a la región indicada.');
                    }
                },
            ],
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'contract_type' => ['nullable', Rule::in(array_column(Staff::CONTRACT_TYPE_OPTIONS, 'value'))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(array_column(Staff::STATUS_OPTIONS, 'value'))],
            'workday' => ['nullable', Rule::in(array_column(Staff::WORKDAY_OPTIONS, 'value'))],
            'contract_hours' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'professional_title' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'professional_registration' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'can_receive_maintenance_orders' => ['sometimes', 'boolean'],
            'maintenance_role' => [
                'nullable',
                'required_if:can_receive_maintenance_orders,true,1',
                Rule::in(array_column(Staff::MAINTENANCE_ROLE_OPTIONS, 'value')),
            ],
            'associated_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($staffId) {
                    if (!$value) {
                        return;
                    }

                    $user = User::query()->find($value);
                    if ($user && $user->staff_id && $user->staff_id !== $staffId) {
                        $fail('El usuario seleccionado ya está asociado a otro funcionario.');
                    }
                },
            ],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'profile_photo' => ['nullable', 'file', 'image', 'max:10240'],
        ];
    }
}
