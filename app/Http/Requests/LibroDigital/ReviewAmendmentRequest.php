<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class ReviewAmendmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.amendments.review') ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:5', 'max:3000'],
        ];
    }
}
