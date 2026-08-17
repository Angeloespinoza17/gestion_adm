<?php
namespace App\Http\Requests\SocialWork;
use App\Models\SocialWork\SocialCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreCaseRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->hasPermission('social_work.cases.create') === true; }
    public function rules(): array { return [
        'primary_student_id' => ['required', 'integer', 'exists:student_profiles,id'], 'student_ids' => ['sometimes', 'array'], 'student_ids.*' => ['integer', 'exists:student_profiles,id'],
        'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'], 'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'], 'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        'title' => ['required', 'string', 'max:255'], 'origin' => ['nullable', 'string', 'max:80'], 'case_type' => ['nullable', 'string', 'max:80'], 'reason' => ['required', 'string', 'max:255'], 'initial_description' => ['nullable', 'string'],
        'received_on' => ['nullable', 'date'], 'opened_on' => ['nullable', 'date'], 'priority' => ['required', Rule::in(SocialCase::PRIORITIES)], 'risk_level' => ['required', Rule::in(SocialCase::RISK_LEVELS)], 'confidentiality' => ['required', Rule::in(SocialCase::CONFIDENTIALITY)],
        'status' => ['sometimes', Rule::in(['borrador', 'recibido'])], 'initial_protocol' => ['nullable', 'string'], 'initial_safeguards' => ['nullable', 'string'], 'next_milestone' => ['nullable', 'string', 'max:255'], 'due_at' => ['nullable', 'date'],
    ]; }
}
