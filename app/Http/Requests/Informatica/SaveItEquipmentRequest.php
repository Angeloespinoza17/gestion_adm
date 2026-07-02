<?php

namespace App\Http\Requests\Informatica;

use App\Models\It\ItEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $equipmentId = $this->route('equipment')?->id;

        return [
            'internal_code' => ['required', 'string', 'max:80', Rule::unique('it_equipment', 'internal_code')->ignore($equipmentId)],
            'equipment_type' => ['required', Rule::in(ItEquipment::TYPE_OPTIONS)],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:160'],
            'serial_number' => ['nullable', 'string', 'max:120', Rule::unique('it_equipment', 'serial_number')->ignore($equipmentId)],
            'status' => ['required', Rule::in(ItEquipment::STATUS_OPTIONS)],
            'location_name' => ['nullable', 'string', 'max:191'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_name' => ['nullable', 'string', 'max:191'],
            'acquisition_date' => ['nullable', 'date'],
            'reference_value' => ['nullable', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
