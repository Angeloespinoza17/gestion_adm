<?php

namespace App\Http\Requests\Informatica;

use App\Models\It\ItEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeItEquipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ItEquipment::STATUS_OPTIONS)],
            'notes' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
