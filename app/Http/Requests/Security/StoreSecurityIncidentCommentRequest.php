<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecurityIncidentCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string'],
            'status_id' => ['nullable', 'integer', Rule::exists('security_incident_statuses', 'id')],
            'assigned_to_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_internal' => ['nullable', 'boolean'],
            'evidence_files' => ['nullable', 'array'],
            'evidence_files.*' => ['file', 'image', 'max:5120'],
        ];
    }
}
