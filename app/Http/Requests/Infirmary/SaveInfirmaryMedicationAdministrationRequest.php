<?php

namespace App\Http\Requests\Infirmary;

use App\Models\Infirmary\InfirmaryMedicationAdministration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryMedicationAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $status = $this->input('administration_status', InfirmaryMedicationAdministration::STATUS_ADMINISTRADA);

        return [
            'authorization_id' => ['nullable', 'integer', 'exists:infirmary_medication_authorizations,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:infirmary_medication_schedules,id'],
            'attention_id' => ['nullable', 'integer', 'exists:infirmary_attentions,id'],
            'medication_id' => [
                'required',
                'integer',
                Rule::exists('infirmary_medications', 'id')
                    ->where(fn ($query) => $query->where('inventory_type', 'medication')),
            ],
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'administered_at' => ['required', 'date'],
            'administration_status' => ['required', Rule::in([
                InfirmaryMedicationAdministration::STATUS_ADMINISTRADA,
                InfirmaryMedicationAdministration::STATUS_NO_ADMINISTRADA,
            ])],
            'administered_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'quantity_administered' => [
                Rule::requiredIf($status === InfirmaryMedicationAdministration::STATUS_ADMINISTRADA),
                'nullable',
                'numeric',
                $status === InfirmaryMedicationAdministration::STATUS_ADMINISTRADA ? 'min:0.01' : 'min:0',
            ],
            'dose_amount' => ['nullable', 'numeric', 'min:0.01'],
            'dose_unit' => ['nullable', Rule::in(['mg', 'cc'])],
            'administration_route' => ['nullable', Rule::in(['oral', 'topica'])],
            'schedule_reference' => ['nullable', 'string', 'max:120'],
            'non_administration_reason' => [
                Rule::requiredIf($status === InfirmaryMedicationAdministration::STATUS_NO_ADMINISTRADA),
                'nullable',
                'string',
                'max:191',
            ],
            'source_type' => ['nullable', Rule::in(['atencion', 'autorizacion'])],
            'observations' => ['nullable', 'string'],
        ];
    }
}
