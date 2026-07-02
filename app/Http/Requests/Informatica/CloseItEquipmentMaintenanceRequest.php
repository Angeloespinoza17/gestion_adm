<?php

namespace App\Http\Requests\Informatica;

use App\Models\It\ItEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseItEquipmentMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closed_at' => ['nullable', 'date'],
            'final_equipment_status' => ['required', Rule::in(ItEquipment::STATUS_OPTIONS)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
