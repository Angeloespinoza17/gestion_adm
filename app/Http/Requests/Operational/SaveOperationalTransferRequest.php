<?php

namespace App\Http\Requests\Operational;

use App\Models\Operational\OperationalTransferRequest;
use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOperationalTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = ['transport_date' => DateInput::normalize($this->input('transport_date'))];
        foreach (['departure_time', 'return_time'] as $field) {
            if (is_string($this->input($field)) && trim($this->input($field)) !== '') {
                $data[$field] = substr(trim($this->input($field)), 0, 5);
            }
        }
        if (blank($this->input('requester_staff_id')) && $this->user()?->staff_id) {
            $data['requester_staff_id'] = $this->user()->staff_id;
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'requester_staff_id' => ['required', 'integer', 'exists:staff,id'],
            'activity_type' => ['required', Rule::in(array_column(OperationalTransferRequest::ACTIVITY_TYPE_OPTIONS, 'value'))],
            'activity_name' => ['required', 'string', 'max:255'],
            'course_subject' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'transport_date' => ['required', 'date'],
            'departure_time' => ['required', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i', 'after:departure_time'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'transport_mode' => ['required', Rule::in(array_column(OperationalTransferRequest::TRANSPORT_MODE_OPTIONS, 'value'))],
            'student_count' => ['required', 'integer', 'min:0', 'max:999'],
            'adult_count' => ['required', 'integer', 'min:0', 'max:999'],
            'reduced_mobility' => ['sometimes', 'boolean'],
            'mobility_requirements' => ['nullable', 'string', 'max:3000'],
            'visible_observations' => ['nullable', 'string', 'max:5000'],
            'urgent' => ['sometimes', 'boolean'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('reduced_mobility') && blank($this->input('mobility_requirements'))) {
                $validator->errors()->add('mobility_requirements', 'Describe el apoyo o adaptación requerida.');
            }
            if (((int) $this->input('student_count') + (int) $this->input('adult_count')) < 1) {
                $validator->errors()->add('adult_count', 'El traslado debe tener al menos una persona.');
            }
            if ($this->input('transport_mode') === 'ida_vuelta' && blank($this->input('return_time'))) {
                $validator->errors()->add('return_time', 'La modalidad ida y vuelta requiere hora de regreso.');
            }
        });
    }
}
