<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class QueryGeoVictoriaAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'staff_ids' => ['required', 'array', 'min:1', 'max:50'],
            'staff_ids.*' => ['required', 'integer', 'distinct', 'exists:staff,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['date_from', 'date_to'])) {
                return;
            }

            $from = Carbon::createFromFormat('Y-m-d', (string) $this->input('date_from'));
            $to = Carbon::createFromFormat('Y-m-d', (string) $this->input('date_to'));

            if ($from->diffInDays($to) > 31) {
                $validator->errors()->add('date_to', 'El rango de consulta no puede superar 31 días.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'staff_ids.required' => 'Selecciona al menos un funcionario.',
            'staff_ids.min' => 'Selecciona al menos un funcionario.',
            'staff_ids.max' => 'Puedes consultar hasta 50 funcionarios a la vez.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ];
    }
}
