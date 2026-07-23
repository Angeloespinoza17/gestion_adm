<?php

namespace App\Http\Requests\CentroApuntes;

use App\Http\Requests\CentroApuntes\Concerns\NormalizesNullableFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePanolEntregaStatusRequest extends FormRequest
{
    use NormalizesNullableFields;

    protected function prepareForValidation(): void
    {
        $this->normalizeNullableFields(['notes', 'withdrawn_by_user_id']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'withdrawn_by_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('user_type', 'staff')->where('active', true))],
        ];
    }
}
