<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class ApplyAmendmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.amendments.apply') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
