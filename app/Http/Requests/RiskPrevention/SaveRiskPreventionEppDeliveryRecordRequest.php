<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;

class SaveRiskPreventionEppDeliveryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'employee_name' => ['required', 'string', 'max:160'],
            'employee_rut' => ['nullable', 'string', 'max:30'],
            'employee_position' => ['nullable', 'string', 'max:160'],
            'delivered_at' => ['required', 'date'],
            'received_conformity' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.epp_item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:prevent_epp_items,id',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.replacement_due_at' => ['nullable', 'date', 'after_or_equal:delivered_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un elemento de protección personal.',
            'items.min' => 'Agrega al menos un elemento de protección personal.',
            'items.*.epp_item_id.distinct' => 'Cada EPP debe aparecer una sola vez en el acta.',
            'items.*.replacement_due_at.after_or_equal' => 'La reposición no puede ser anterior a la entrega.',
        ];
    }
}
