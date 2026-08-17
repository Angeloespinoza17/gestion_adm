<?php
namespace App\Http\Requests\SocialWork;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreInterventionRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->hasPermission('social_work.actions.manage') || $this->user()?->hasPermission('social_work.interviews.manage'); }
    public function rules(): array { return [
        'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'], 'kind' => ['required', Rule::in(['entrevista', 'accion', 'visita_domiciliaria', 'llamado', 'accion_familiar', 'reunion', 'seguimiento'])], 'type' => ['nullable', 'string', 'max:80'],
        'activity_date' => ['required', 'date'], 'starts_at' => ['nullable', 'date_format:H:i'], 'ends_at' => ['nullable', 'date_format:H:i', 'after_or_equal:starts_at'], 'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        'modality' => ['nullable', 'string', 'max:40'], 'place' => ['nullable', 'string', 'max:255'], 'contact_number' => ['nullable', 'string', 'max:60'], 'contact_person' => ['nullable', 'string', 'max:255'], 'contact_relationship' => ['nullable', 'string', 'max:80'], 'contact_result' => ['nullable', 'string', 'max:60'],
        'objective' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'professional_observations' => ['nullable', 'string'], 'highly_confidential_notes' => ['nullable', 'string'], 'result' => ['nullable', 'string'], 'agreements' => ['nullable', 'string'], 'next_action' => ['nullable', 'string'], 'due_at' => ['nullable', 'date'], 'next_intervention_at' => ['nullable', 'date'],
        'status' => ['required', Rule::in(['borrador', 'finalizada', 'anulada'])], 'confidentiality' => ['required', Rule::in(['interno', 'restringido', 'altamente_restringido'])], 'participants' => ['nullable', 'array'], 'structured_data' => ['nullable', 'array'], 'commitments' => ['nullable', 'array'], 'commitments.*.description' => ['required', 'string'], 'commitments.*.due_at' => ['nullable', 'date'],
    ]; }
}
