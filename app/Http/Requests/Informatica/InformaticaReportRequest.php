<?php

namespace App\Http\Requests\Informatica;

use App\Models\It\ItEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InformaticaReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'semestral', 'annual'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'it_equipment_id' => ['nullable', 'integer', 'exists:it_equipment,id'],
            'equipment_type' => ['nullable', Rule::in(ItEquipment::TYPE_OPTIONS)],
            'status' => ['nullable', Rule::in(ItEquipment::STATUS_OPTIONS)],
        ];
    }
}
