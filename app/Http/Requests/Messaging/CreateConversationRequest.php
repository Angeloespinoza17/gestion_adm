<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->active;
    }

    public function rules(): array
    {
        return ['user_id' => ['sometimes', 'integer', 'exists:users,id'], 'title' => ['sometimes', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'user_ids' => ['sometimes', 'array', 'max:5000'], 'user_ids.*' => ['integer', 'distinct', 'exists:users,id'], 'role_ids' => ['sometimes', 'array'], 'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'], 'audience_all' => ['sometimes', 'boolean'], 'only_admins_can_write' => ['sometimes', 'boolean']];
    }
}
