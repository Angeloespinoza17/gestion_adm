<?php

namespace App\Http\Requests\Staff;

use App\Models\Commune;
use App\Models\Staff;
use App\Models\User;
use App\Support\DateInput;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $institutionalEmail = $this->input('institutional_email');
        $personalEmail = $this->input('personal_email');
        $departmentIds = $this->input('department_ids');

        $data = [
            'rut' => Rut::normalize($this->input('rut')),
            'birth_date' => DateInput::normalize($this->input('birth_date')),
            'institutional_email' => $institutionalEmail ? mb_strtolower(trim((string) $institutionalEmail)) : null,
            'personal_email' => $personalEmail ? mb_strtolower(trim((string) $personalEmail)) : null,
            'start_date' => DateInput::normalize($this->input('start_date')),
            'end_date' => DateInput::normalize($this->input('end_date')),
            'active' => $this->has('active')
                ? filter_var($this->input('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true,
            'can_receive_maintenance_orders' => $this->has('can_receive_maintenance_orders')
                ? filter_var($this->input('can_receive_maintenance_orders'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : false,
            'maintenance_role' => $this->input('maintenance_role') ?: null,
            'status' => $this->input('status') ?: 'activo',
        ];

        if (is_string($departmentIds)) {
            $decoded = json_decode($departmentIds, true);
            $data['department_ids'] = is_array($decoded) ? $decoded : [];
        }

        if (($this->input('contract_type') ?: null) === 'indefinido') {
            $data['end_date'] = null;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'rut' => [
                'nullable',
                'string',
                'max:20',
                'unique:staff,rut',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                        $fail('El RUT ingresado no es válido.');
                    }
                },
            ],
            'birth_date' => ['nullable', 'date'],
            'institutional_email' => ['nullable', 'email', 'max:255', 'unique:staff,institutional_email'],
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
            'status' => ['required', Rule::in(array_column(Staff::STATUS_OPTIONS, 'value'))],
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
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

                    $user = User::query()->find($value);
                    if ($user && $user->staff_id) {
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
