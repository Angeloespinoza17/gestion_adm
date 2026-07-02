<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRiskPreventionEppDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'epp_item_id' => ['required', 'integer', 'exists:prevent_epp_items,id'],
            'employee_name' => ['required', 'string', 'max:160'],
            'quantity' => ['required', 'integer', 'min:1'],
            'delivered_at' => ['required', 'date'],
            'replacement_due_at' => ['nullable', 'date', 'after_or_equal:delivered_at'],
            'status' => ['required', Rule::in(['vigente', 'por_reponer', 'repuesto'])],
            'observations' => ['nullable', 'string'],
        ];
    }
}
