<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Validation\Rule;

class ValidateCanvaTemplateRequest extends CanvaSchoolRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'slide_count' => ['required', 'integer', Rule::in((array) config('class_presentations.options.slide_count', []))],
        ];
    }
}
