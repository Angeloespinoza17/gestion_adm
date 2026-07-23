<?php

namespace App\Http\Requests\CentroApuntes;

use App\Http\Requests\CentroApuntes\Concerns\NormalizesNullableFields;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeCentroApuntesSolicitudStatusRequest extends FormRequest
{
    use NormalizesNullableFields;

    protected function prepareForValidation(): void
    {
        $this->normalizeNullableFields(['notes']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(CentroApuntesSolicitud::STATUS_OPTIONS)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
