<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GenerateGeoVictoriaReportsRequest extends FormRequest
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
            'scope' => ['required', Rule::in(['all_linked', 'selected'])],
            'staff_ids' => ['nullable', 'array', 'max:50', 'required_if:scope,selected'],
            'staff_ids.*' => ['required', 'integer', 'distinct', 'exists:staff,id'],
            'tolerance_minutes' => ['sometimes', 'integer', 'min:0', 'max:60'],
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
                $validator->errors()->add('date_to', 'El rango del reporte no puede superar 31 días.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'scope.in' => 'Selecciona un alcance válido para el reporte.',
            'staff_ids.required_if' => 'Selecciona al menos un funcionario para este alcance.',
            'staff_ids.max' => 'Puedes incluir hasta 50 funcionarios en un reporte seleccionado.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ];
    }
}
