<?php

namespace App\Http\Requests\PedagogicalManagement;

class CanvaAuthorizationRequest extends CanvaSchoolRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'redirect_to' => ['nullable', 'string', 'max:500', 'regex:/^\/(?!\/)[^\x00-\x1F\x7F]*$/'],
        ];
    }
}
