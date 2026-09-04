<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Foundation\Http\FormRequest;

class CanvaSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('class-presentations.create') === true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
        ];
    }
}
