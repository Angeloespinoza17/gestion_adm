<?php

namespace App\Http\Requests\CentroApuntes;

use App\Models\CentroApuntes\CentroApuntesSolicitud;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeCentroApuntesSolicitudStatusRequest extends FormRequest
{
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
