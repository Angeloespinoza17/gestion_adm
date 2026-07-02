<?php

namespace App\Http\Requests\Porter;

use App\Models\PorterGoodsMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePorterGoodsMovementStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_column(PorterGoodsMovement::STATUS_OPTIONS, 'value'))],
            'received_by_name' => ['nullable', 'string', 'max:191'],
            'received_by_identifier' => ['nullable', 'string', 'max:120'],
            'delivery_observations' => ['nullable', 'string'],
        ];
    }
}
