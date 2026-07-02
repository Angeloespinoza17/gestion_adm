<?php

namespace App\Http\Requests\Porter;

use App\Models\PorterGoodsMovement;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePorterGoodsMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contact_rut' => Rut::normalize($this->input('contact_rut')),
        ]);
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['required', Rule::in(array_column(PorterGoodsMovement::MOVEMENT_TYPE_OPTIONS, 'value'))],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'contact_name' => ['required', 'string', 'max:191'],
            'contact_rut' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'company' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'goods_detail' => ['required', 'string'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'document_type' => ['nullable', Rule::in(array_column(PorterGoodsMovement::DOCUMENT_TYPE_OPTIONS, 'value'))],
            'document_number' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_column(PorterGoodsMovement::STATUS_OPTIONS, 'value'))],
            'observations' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ];
    }
}
