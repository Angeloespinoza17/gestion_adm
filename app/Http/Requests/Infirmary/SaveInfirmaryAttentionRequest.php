<?php

namespace App\Http\Requests\Infirmary;

use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\Infirmary\InfirmaryAttentionTreatment;
use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryAttentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_type' => ['required', Rule::in([InfirmaryAttention::SUBJECT_STUDENT, InfirmaryAttention::SUBJECT_STAFF])],
            'student_profile_id' => [
                Rule::requiredIf(fn () => $this->input('subject_type') === InfirmaryAttention::SUBJECT_STUDENT),
                'nullable',
                'integer',
                'exists:student_profiles,id',
            ],
            'staff_id' => [
                Rule::requiredIf(fn () => $this->input('subject_type') === InfirmaryAttention::SUBJECT_STAFF),
                'nullable',
                'integer',
                'exists:staff,id',
            ],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'teacher_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'referred_by_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'dependency_id' => [
                Rule::requiredIf(fn () => $this->isAccidentCategory() && $this->input('accident_location_type') === 'colegio'),
                'nullable',
                'integer',
                'exists:maintenance_dependencies,id',
            ],
            'attended_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'attention_category' => ['required', 'string', 'max:120'],
            'accident_location_type' => [
                Rule::requiredIf(fn () => $this->isAccidentCategory()),
                'nullable',
                Rule::in(array_column(InfirmaryAttention::ACCIDENT_LOCATION_OPTIONS, 'value')),
            ],
            'occurred_at' => ['required', 'date', 'before_or_equal:attended_at'],
            'attended_at' => ['required', 'date', 'before_or_equal:now'],
            'accompanied_by_type' => ['required', Rule::in(array_column(InfirmaryAttention::COMPANION_OPTIONS, 'value'))],
            'accompanied_by_staff_id' => [
                Rule::requiredIf(fn () => $this->requiresCompanionStaff()),
                'nullable',
                'integer',
                $this->companionStaffRule(),
            ],
            'accompanied_by_name' => ['nullable', 'string', 'max:160'],
            'consultation_reason' => ['required', 'string'],
            'accident_circumstance' => ['nullable', 'string'],
            'logbook' => ['nullable', 'string'],
            'initial_description' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'attention_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'priority' => ['required', Rule::in(['baja', 'media', 'alta', 'emergencia'])],
            'status' => ['required', Rule::in(['abierta', 'en_atencion', 'finalizada'])],

            'treatments' => ['sometimes', 'array'],
            'treatments.*.treatment_categories' => ['nullable', 'array'],
            'treatments.*.treatment_categories.*' => ['string', Rule::in(array_column(InfirmaryAttentionTreatment::CATEGORY_OPTIONS, 'value'))],
            'treatments.*.treatment_types' => ['nullable', 'array'],
            'treatments.*.treatment_types.*' => ['string', Rule::in(InfirmaryAttentionTreatment::TYPE_OPTIONS)],
            'treatments.*.derivation_type' => ['nullable', 'string', Rule::in(array_column(InfirmaryAttentionTreatment::DERIVATION_TYPE_OPTIONS, 'value'))],
            'treatments.*.derivation_support_teams' => ['nullable', 'array'],
            'treatments.*.derivation_support_teams.*' => ['string', Rule::in(array_column(InfirmaryAttentionTreatment::DERIVATION_SUPPORT_TEAM_OPTIONS, 'value'))],
            'treatments.*.treatment_other' => ['nullable', 'string', 'max:160'],
            'treatments.*.medication_id' => [
                'nullable',
                'integer',
                Rule::exists('infirmary_medications', 'id')
                    ->where(fn ($query) => $query->where('inventory_type', 'medication')),
            ],
            'treatments.*.medication_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'treatments.*.blood_pressure' => ['nullable', 'string', 'max:40'],
            'treatments.*.pulse' => ['nullable', 'integer', 'min:1', 'max:300'],
            'treatments.*.respiratory_rate' => ['nullable', 'integer', 'min:1', 'max:120'],
            'treatments.*.temperature' => ['nullable', 'numeric', 'min:20', 'max:45'],
            'treatments.*.oxygen_saturation' => ['nullable', 'integer', 'min:1', 'max:100'],
            'treatments.*.weight' => ['nullable', 'numeric', 'min:1', 'max:300'],
            'treatments.*.height' => ['nullable', 'numeric', 'min:0.3', 'max:250'],
            'treatments.*.vital_signs_notes' => ['nullable', 'string'],
            'treatments.*.emotional_support_required' => ['nullable', 'boolean'],
            'treatments.*.emotional_comment' => ['nullable', 'string'],
            'treatments.*.emotional_support_type' => ['nullable', 'string', 'max:160'],
            'treatments.*.emotional_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'treatments.*.emotional_professional_id' => ['nullable', 'integer', 'exists:staff,id'],
            'treatments.*.other_treatments' => ['nullable', 'string'],
            'treatments.*.notes' => ['nullable', 'string'],

            'referrals' => ['sometimes', 'array'],
            'referrals.*.referral_type' => ['required', 'string', Rule::in(InfirmaryAttentionReferral::TYPE_OPTIONS)],
            'referrals.*.referred_at' => ['required', 'date'],
            'referrals.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'referrals.*.responsible_name' => ['nullable', 'string', 'max:160'],
            'referrals.*.reason' => ['nullable', 'string'],
            'referrals.*.observations' => ['nullable', 'string'],
            'referrals.*.result' => ['nullable', 'string'],

            'calls' => ['sometimes', 'array'],
            'calls.*.called_at' => ['required', 'date'],
            'calls.*.person_contacted' => ['required', 'string', 'max:160'],
            'calls.*.relationship' => ['nullable', 'string', 'max:120'],
            'calls.*.phone_number' => ['nullable', 'string', 'max:50'],
            'calls.*.call_status' => ['required', Rule::in(['pendiente', 'contesto', 'no_contesto', 'mensaje_dejado'])],
            'calls.*.reason' => ['nullable', 'string', 'max:191'],
            'calls.*.conversation_summary' => ['nullable', 'string'],
            'calls.*.commitments' => ['nullable', 'string'],
            'calls.*.estimated_arrival_at' => ['nullable', 'date'],
            'calls.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'calls.*.called_by_user_id' => ['nullable', 'integer', 'exists:users,id'],

            'follow_ups' => ['sometimes', 'array'],
            'follow_ups.*.followed_at' => ['required', 'date'],
            'follow_ups.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'follow_ups.*.comment' => ['required', 'string'],
            'follow_ups.*.status' => ['required', Rule::in(['pendiente', 'en_proceso', 'cerrado'])],
            'follow_ups.*.next_review_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'occurred_at.required' => 'Ingresa la fecha de accidente.',
            'occurred_at.before_or_equal' => 'La fecha de accidente no puede ser posterior a la fecha de registro.',
            'attended_at.required' => 'Ingresa la fecha de registro.',
            'attended_at.before_or_equal' => 'La fecha de registro no puede estar en el futuro.',
            'accompanied_by_staff_id.required' => 'Selecciona la funcionaria que acompaña.',
            'student_profile_id.required' => 'Selecciona una estudiante.',
            'staff_id.required' => 'Selecciona un funcionario.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['subject_type' => $this->attentionSubjectType()]);
    }

    protected function attentionSubjectType(): string
    {
        return InfirmaryAttention::SUBJECT_STUDENT;
    }

    private function companionStaffRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! $value || ! $this->requiresCompanionStaff()) {
                return;
            }

            $allowedCargoSlugs = InfirmaryAttention::STAFF_COMPANION_CARGO_SLUGS[$this->input('accompanied_by_type')] ?? [];

            $exists = Staff::query()
                ->whereKey($value)
                ->where('active', true)
                ->whereHas('cargo', fn ($query) => $query->whereIn('slug', $allowedCargoSlugs))
                ->exists();

            if (! $exists) {
                $fail('La funcionaria seleccionada no corresponde al tipo de acompañante.');
            }
        };
    }

    private function requiresCompanionStaff(): bool
    {
        return array_key_exists(
            (string) $this->input('accompanied_by_type'),
            InfirmaryAttention::STAFF_COMPANION_CARGO_SLUGS
        );
    }

    private function isAccidentCategory(): bool
    {
        return in_array($this->input('attention_category'), ['accidente_menor', 'accidente_mayor'], true);
    }
}
