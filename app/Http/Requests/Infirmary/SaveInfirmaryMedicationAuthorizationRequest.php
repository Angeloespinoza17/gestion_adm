<?php

namespace App\Http\Requests\Infirmary;

use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryMedicationAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSos = $this->input('regimen_type') === InfirmaryMedicationAuthorization::REGIMEN_SOS;
        $dailyDoseCount = max(1, (int) $this->input('daily_dose_count', 1));
        $hasFixedTimes = ! $isSos
            && $this->input('schedule_mode') === InfirmaryMedicationAuthorization::SCHEDULE_FIXED_TIME;
        $durationRegimens = [
            InfirmaryMedicationAuthorization::REGIMEN_MESES,
            InfirmaryMedicationAuthorization::REGIMEN_SEMANAS,
            InfirmaryMedicationAuthorization::REGIMEN_DIAS,
        ];

        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'medication_id' => [
                'required',
                'integer',
                Rule::exists('infirmary_medications', 'id')
                    ->where(fn ($query) => $query->where('inventory_type', 'medication')),
            ],
            'diagnosis' => ['nullable', 'string'],
            'dose' => ['required', 'string', 'max:120'],
            'dose_amount' => ['nullable', 'numeric', 'min:0.01'],
            'dose_unit' => ['nullable', Rule::in(['mg', 'cc'])],
            'administration_route' => ['required', Rule::in(['oral', 'topica'])],
            'frequency' => ['nullable', 'string', 'max:120'],
            'daily_dose_count' => [
                Rule::requiredIf(! $isSos),
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],
            'schedule_mode' => [
                Rule::requiredIf(! $isSos),
                'nullable',
                Rule::in([
                    InfirmaryMedicationAuthorization::SCHEDULE_FIXED_TIME,
                    InfirmaryMedicationAuthorization::SCHEDULE_FLEXIBLE,
                ]),
            ],
            'schedule_text' => ['nullable', 'string', 'max:191'],
            'schedules' => [
                Rule::requiredIf(! $isSos),
                'array',
                $isSos ? 'max:0' : "size:{$dailyDoseCount}",
            ],
            'schedules.*.dose_order' => ['required', 'integer', 'min:1', 'max:12', 'distinct'],
            'schedules.*.scheduled_time' => [
                Rule::requiredIf($hasFixedTimes),
                'nullable',
                'date_format:H:i',
                'distinct',
            ],
            'regimen_type' => ['required', Rule::in([
                InfirmaryMedicationAuthorization::REGIMEN_PERMANENTE,
                InfirmaryMedicationAuthorization::REGIMEN_MESES,
                InfirmaryMedicationAuthorization::REGIMEN_SEMANAS,
                InfirmaryMedicationAuthorization::REGIMEN_DIAS,
                InfirmaryMedicationAuthorization::REGIMEN_FECHA_ESPECIFICA,
                InfirmaryMedicationAuthorization::REGIMEN_SOS,
            ])],
            'duration_quantity' => [
                Rule::requiredIf(in_array($this->input('regimen_type'), $durationRegimens, true)),
                'nullable',
                'integer',
                'min:1',
                'max:3650',
            ],
            'start_date' => ['required', 'date'],
            'end_date' => [
                Rule::requiredIf($this->input('regimen_type') === InfirmaryMedicationAuthorization::REGIMEN_FECHA_ESPECIFICA),
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'physician_name' => ['nullable', 'string', 'max:160'],
            'medical_authorization_expires_at' => ['nullable', 'date'],
            'guardian_authorization_expires_at' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['vigente', 'proxima_a_vencer', 'vencida', 'terminada'])],
        ];
    }

    public function messages(): array
    {
        return [
            'daily_dose_count.required' => 'Debes indicar cuántas dosis se administran cada día.',
            'daily_dose_count.max' => 'La frecuencia diaria no puede superar 12 dosis.',
            'schedules.size' => 'La cantidad de horarios debe coincidir con la frecuencia diaria.',
            'schedules.*.scheduled_time.required' => 'Debes indicar la hora de cada dosis.',
            'schedules.*.scheduled_time.distinct' => 'Los horarios de las dosis no pueden repetirse.',
        ];
    }
}
