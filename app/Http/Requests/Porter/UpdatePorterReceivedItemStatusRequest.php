<?php

namespace App\Http\Requests\Porter;

use App\Models\PorterReceivedItem;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePorterReceivedItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'delivered_to_rut' => Rut::normalize($this->input('delivered_to_rut')),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_column(PorterReceivedItem::STATUS_OPTIONS, 'value'))],
            'delivered_to_name' => ['nullable', 'string', 'max:191'],
            'delivered_to_rut' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'delivery_observations' => ['nullable', 'string'],
        ];
    }
}
