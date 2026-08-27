<?php

namespace App\Http\Requests\PedagogicalManagement;

use App\Enums\PedagogicalManagement\EvaluationPurpose;
use App\Enums\PedagogicalManagement\InstrumentStatus;
use App\Enums\PedagogicalManagement\InstrumentWorkflowStatus;
use App\Enums\PedagogicalManagement\InstrumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPedagogicalInstrumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('pedagogical-instruments.view') === true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
            'subject_id' => ['sometimes', 'integer', 'exists:schedule_subjects,id'],
            'owner_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'instrument_type' => ['sometimes', Rule::enum(InstrumentType::class)],
            'evaluation_purpose' => ['sometimes', Rule::enum(EvaluationPurpose::class)],
            'status' => ['sometimes', Rule::enum(InstrumentStatus::class)],
            'workflow_status' => ['sometimes', Rule::enum(InstrumentWorkflowStatus::class)],
            'scope' => ['sometimes', Rule::in(['mine'])],
            'search' => ['sometimes', 'string', 'max:120'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'between:10,100'],
        ];
    }
}
