<?php

namespace App\Http\Requests\Informatica;

use App\Models\It\ItEquipmentMaintenanceReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItEquipmentMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reportId = $this->route('report')?->id;

        return [
            'maintenance_code' => ['nullable', 'string', 'max:80', Rule::unique('it_equipment_maintenance_reports', 'maintenance_code')->ignore($reportId)],
            'it_equipment_id' => ['required', 'integer', 'exists:it_equipment,id'],
            'maintenance_date' => ['required', 'date'],
            'maintenance_type' => ['required', Rule::in(ItEquipmentMaintenanceReport::TYPE_OPTIONS)],
            'technician_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'technician_name' => ['nullable', 'string', 'max:191'],
            'reason' => ['required', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'actions_performed' => ['nullable', 'string'],
            'spare_parts' => ['nullable', 'string'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'initial_equipment_status' => ['nullable', Rule::in(['disponible', 'prestado', 'en_mantencion', 'danado', 'dado_de_baja'])],
            'next_maintenance_at' => ['nullable', 'date', 'after_or_equal:maintenance_date'],
            'observations' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['borrador', 'finalizado', 'pendiente_revision'])],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ];
    }
}
