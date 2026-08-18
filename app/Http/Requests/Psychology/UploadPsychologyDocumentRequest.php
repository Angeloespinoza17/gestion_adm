<?php

namespace App\Http\Requests\Psychology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPsychologyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['document' => ['required', 'file', 'max:'.config('psychology.max_file_kb', 10240), 'mimetypes:'.implode(',', config('psychology.allowed_mimes', []))], 'case_id' => ['nullable', 'integer', 'exists:psychology_cases,id'], 'referral_id' => ['nullable', 'integer', 'exists:psychology_referrals,id'], 'activity_id' => ['nullable', 'integer', 'exists:psychology_activities,id'], 'category' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500'], 'visibility' => ['required', Rule::in(['private_psychology', 'psychology_team', 'interdisciplinary_team', 'referral_feedback'])]];
    }
}
