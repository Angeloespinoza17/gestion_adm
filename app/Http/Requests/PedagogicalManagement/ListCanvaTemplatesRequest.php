<?php

namespace App\Http\Requests\PedagogicalManagement;

class ListCanvaTemplatesRequest extends CanvaSchoolRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'query' => ['nullable', 'string', 'max:100'],
            'continuation' => ['nullable', 'string', 'max:2000'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
