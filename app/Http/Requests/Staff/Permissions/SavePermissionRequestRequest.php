<?php

namespace App\Http\Requests\Staff\Permissions;

use App\Models\PermissionType;
use App\Support\DateInput;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class SavePermissionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'start_date' => DateInput::normalize($this->input('start_date')),
            'end_date' => DateInput::normalize($this->input('end_date')),
        ];

        foreach (['department_ids'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $data[$field] = is_array($decoded) ? $decoded : [];
            }
        }

        foreach (['start_time', 'end_time'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && trim($value) !== '') {
                $data[$field] = substr(trim($value), 0, 5);
            }
        }

        if (blank($this->input('staff_id')) && $this->user()?->staff_id) {
            $data['staff_id'] = $this->user()->staff_id;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'permission_type_id' => ['required', 'integer', 'exists:permission_types,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'direct_manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'is_full_day' => ['sometimes', 'boolean'],
            'is_half_day' => ['sometimes', 'boolean'],
            'with_pay' => ['nullable', 'boolean'],
            'affects_salary' => ['sometimes', 'boolean'],
            'affects_attendance' => ['sometimes', 'boolean'],
            'requires_replacement' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'employee_observations' => ['nullable', 'string'],
            'visible_observations' => ['nullable', 'string'],
            'internal_observations' => ['nullable', 'string'],
            'urgency' => ['sometimes', 'boolean'],
            'retroactive' => ['sometimes', 'boolean'],
            'salary_discount_hours' => ['nullable', 'numeric', 'min:0'],
            'salary_discount_days' => ['nullable', 'numeric', 'min:0'],
            'requires_regularization' => ['sometimes', 'boolean'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'staff_id.required' => 'Tu usuario debe estar asociado a una ficha de funcionario para crear solicitudes.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $permissionType = PermissionType::query()->find($this->input('permission_type_id'));

            if (!$permissionType) {
                return;
            }

            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');
            $startTime = $this->input('start_time');
            $endTime = $this->input('end_time');
            $isHalfDay = $this->boolean('is_half_day');
            $urgency = $this->boolean('urgency');
            $retroactive = $this->boolean('retroactive');

            if (($startTime && !$endTime) || (!$startTime && $endTime)) {
                $validator->errors()->add('end_time', 'Debes indicar hora de inicio y término.');
            }

            if ($startTime && $endTime) {
                if (!$permissionType->allows_hourly) {
                    $validator->errors()->add('start_time', 'Este tipo de permiso no admite solicitudes por horas.');
                }

                if ($startDate === $endDate && $endTime <= $startTime) {
                    $validator->errors()->add('end_time', 'La hora de término debe ser posterior a la hora de inicio.');
                }
            }

            if ($isHalfDay) {
                if (!$permissionType->allows_half_day) {
                    $validator->errors()->add('is_half_day', 'Este tipo de permiso no admite media jornada.');
                }

                if ($startDate !== $endDate) {
                    $validator->errors()->add('end_date', 'La media jornada solo puede registrarse para un día.');
                }
            }

            if ($isHalfDay && ($startTime || $endTime)) {
                $validator->errors()->add('is_half_day', 'La media jornada no puede combinarse con rango horario.');
            }

            if ($this->filled('with_pay')) {
                $withPay = filter_var($this->input('with_pay'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($withPay === true && !$permissionType->allows_with_pay) {
                    $validator->errors()->add('with_pay', 'Este tipo de permiso no permite goce de remuneraciones.');
                }

                if ($withPay === false && !$permissionType->allows_without_pay) {
                    $validator->errors()->add('with_pay', 'Este tipo de permiso no permite registrarse sin goce de remuneraciones.');
                }
            }

            if ($permissionType->minimum_notice_days && !$urgency && $startDate) {
                $noticeDays = Carbon::today()->diffInDays(Carbon::parse($startDate), false);

                if ($noticeDays < $permissionType->minimum_notice_days) {
                    $validator->errors()->add('start_date', 'La solicitud no cumple con la anticipación mínima requerida.');
                }
            }

            if ($retroactive && !$permissionType->allows_retroactive && !$urgency) {
                $validator->errors()->add('retroactive', 'Este tipo de permiso no permite ingreso retroactivo.');
            }

            if ($urgency && blank($this->input('employee_observations'))) {
                $validator->errors()->add('employee_observations', 'Debes indicar una observación obligatoria para justificar la urgencia.');
            }

            $requestedDays = $this->requestedDays();

            if ($permissionType->max_days !== null && $requestedDays > (float) $permissionType->max_days) {
                $validator->errors()->add('end_date', 'La solicitud supera el máximo permitido para este tipo de permiso.');
            }
        });
    }

    private function requestedDays(): float
    {
        if ($this->boolean('is_half_day')) {
            return 0.5;
        }

        if ($this->filled('start_time') && $this->filled('end_time')) {
            $start = Carbon::parse($this->input('start_date') . ' ' . $this->input('start_time'));
            $end = Carbon::parse($this->input('end_date') . ' ' . $this->input('end_time'));

            return round($start->diffInMinutes($end) / 60 / 8, 2);
        }

        return (float) Carbon::parse($this->input('start_date'))->diffInDays(Carbon::parse($this->input('end_date'))) + 1;
    }
}
