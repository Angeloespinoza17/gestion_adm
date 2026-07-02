<?php

namespace App\Http\Requests\Security;

use App\Models\Security\SecurityIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSecurityIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_id' => ['nullable', 'integer', Rule::exists('security_incident_statuses', 'id')],
            'priority' => ['required', Rule::in(array_column(SecurityIncident::PRIORITY_OPTIONS, 'value'))],
            'current_responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'assignee_user_ids' => ['nullable', 'array'],
            'assignee_user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'response_due_at' => ['nullable', 'date'],
            'response_summary' => ['nullable', 'string'],
            'closure_evidence_notes' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
            'is_internal' => ['nullable', 'boolean'],
            'evidence_files' => ['nullable', 'array'],
            'evidence_files.*' => ['file', 'image', 'max:5120'],
        ];
    }
}
