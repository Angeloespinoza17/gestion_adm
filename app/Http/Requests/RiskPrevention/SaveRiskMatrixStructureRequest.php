<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRiskMatrixStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('risk-matrix.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'processes' => ['present', 'array', 'max:200'],
            'processes.*.name' => ['required', 'string', 'max:255'],
            'processes.*.description' => ['nullable', 'string', 'max:5000'],
            'processes.*.process_type' => ['required', Rule::in(['operational', 'support', 'strategic', 'other'])],
            'processes.*.display_order' => ['nullable', 'integer', 'min:0'],
            'processes.*.observations' => ['nullable', 'string', 'max:5000'],
            'processes.*.tasks' => ['present', 'array', 'max:1000'],
            'processes.*.tasks.*.activity_name' => ['required', 'string', 'max:2000'],
            'processes.*.tasks.*.task_name' => ['required', 'string', 'max:2000'],
            'processes.*.tasks.*.routine_type' => ['required', Rule::in(['routine', 'non_routine', 'occasional', 'emergency'])],
            'processes.*.tasks.*.job_position_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'processes.*.tasks.*.position_ids' => ['nullable', 'array'], 'processes.*.tasks.*.position_ids.*' => ['integer', 'exists:cargos,id'],
            'processes.*.tasks.*.job_position_text' => ['nullable', 'string', 'max:2000'],
            'processes.*.tasks.*.location_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'processes.*.tasks.*.specific_location' => ['nullable', 'string', 'max:2000'],
            'processes.*.tasks.*.zero_exposure_justification' => ['nullable', 'string', 'max:2000'],
            'processes.*.tasks.*.observations' => ['nullable', 'string', 'max:5000'],
            'processes.*.tasks.*.exposures' => ['present', 'array', 'max:30'],
            'processes.*.tasks.*.exposures.*.exposure_category_id' => ['required', 'integer', 'exists:prevent_risk_catalog_items,id'],
            'processes.*.tasks.*.exposures.*.count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'processes.*.tasks.*.exposures.*.observations' => ['nullable', 'string', 'max:1000'],
            'processes.*.tasks.*.risks' => ['present', 'array', 'max:2000'],
            'processes.*.tasks.*.risks.*.risk_family_id' => ['required', 'integer', 'exists:prevent_risk_catalog_items,id'],
            'processes.*.tasks.*.risks.*.risk_catalog_item_id' => ['nullable', 'integer', 'exists:prevent_risk_catalog_items,id'],
            'processes.*.tasks.*.risks.*.specific_risk_code' => ['nullable', 'string', 'max:100'],
            'processes.*.tasks.*.risks.*.specific_risk_name' => ['required', 'string', 'max:2000'],
            'processes.*.tasks.*.risks.*.possible_harm' => ['required', 'string', 'max:5000'],
            'processes.*.tasks.*.risks.*.evaluation_method' => ['required', Rule::in(['vep', 'protocol', 'quantitative', 'qualitative', 'external_assessment'])],
            'processes.*.tasks.*.risks.*.declared_controlled_status' => ['nullable', Rule::in(['not_assessed', 'not_controlled', 'partially_controlled', 'controlled', 'unverified_legacy'])],
            'processes.*.tasks.*.risks.*.verified_controlled_status' => ['nullable', Rule::in(['not_assessed', 'not_controlled', 'partially_controlled', 'controlled', 'unverified_legacy'])],
            'processes.*.tasks.*.risks.*.legal_or_protocol_reference' => ['nullable', 'string', 'max:255'],
            'processes.*.tasks.*.risks.*.notes' => ['nullable', 'string', 'max:5000'],
            'processes.*.tasks.*.risks.*.hazard_factors' => ['present', 'array', 'min:1', 'max:30'],
            'processes.*.tasks.*.risks.*.hazard_factors.*.category' => ['nullable', Rule::in(['people', 'equipment', 'materials', 'environment', 'other'])],
            'processes.*.tasks.*.risks.*.hazard_factors.*.hazard_description' => ['required', 'string', 'max:5000'],
            'processes.*.tasks.*.risks.*.hazard_factors.*.risk_factor_description' => ['nullable', 'string', 'max:5000'],
            'processes.*.tasks.*.risks.*.hazard_factors.*.source_catalog_id' => ['nullable', 'integer', 'exists:prevent_risk_catalog_items,id'],
            'processes.*.tasks.*.risks.*.assessments' => ['present', 'array', 'min:1', 'max:20'],
            'processes.*.tasks.*.risks.*.assessments.*.phase' => ['required', Rule::in(['current', 'residual'])],
            'processes.*.tasks.*.risks.*.assessments.*.method' => ['required', Rule::in(['vep', 'protocol', 'quantitative', 'qualitative', 'external_assessment'])],
            'processes.*.tasks.*.risks.*.assessments.*.probability' => ['nullable', 'integer', Rule::in([1, 2, 4])],
            'processes.*.tasks.*.risks.*.assessments.*.consequence' => ['nullable', 'integer', Rule::in([1, 2, 4])],
            'processes.*.tasks.*.risks.*.assessments.*.calculated_score' => ['prohibited'],
            'processes.*.tasks.*.risks.*.assessments.*.calculated_level_id' => ['prohibited'],
            'processes.*.tasks.*.risks.*.assessments.*.protocol_id' => ['nullable', 'integer', 'exists:prevent_risk_catalog_items,id'],
            'processes.*.tasks.*.risks.*.assessments.*.protocol_version' => ['nullable', 'string', 'max:80'],
            'processes.*.tasks.*.risks.*.assessments.*.exposure_value' => ['nullable', 'numeric'],
            'processes.*.tasks.*.risks.*.assessments.*.exposure_unit' => ['nullable', 'string', 'max:80'],
            'processes.*.tasks.*.risks.*.assessments.*.result_value' => ['nullable', 'numeric'],
            'processes.*.tasks.*.risks.*.assessments.*.result_level' => ['nullable', 'string', 'max:100'],
            'processes.*.tasks.*.risks.*.assessments.*.instrument' => ['nullable', 'string', 'max:255'],
            'processes.*.tasks.*.risks.*.assessments.*.evaluator' => ['nullable', 'string', 'max:255'],
            'processes.*.tasks.*.risks.*.assessments.*.instrument_date' => ['nullable', 'date'],
            'processes.*.tasks.*.risks.*.assessments.*.next_measurement_at' => ['nullable', 'date'],
            'processes.*.tasks.*.risks.*.assessments.*.assessment_notes' => ['nullable', 'string', 'max:5000'],
            'processes.*.tasks.*.risks.*.controls' => ['present', 'array', 'max:100'],
            'processes.*.tasks.*.risks.*.controls.*.control_stage' => ['required', Rule::in(['existing', 'proposed', 'corrective', 'preventive'])],
            'processes.*.tasks.*.risks.*.controls.*.hierarchy_type' => ['required', Rule::in(['elimination', 'substitution', 'engineering', 'administrative', 'personal_protective_equipment'])],
            'processes.*.tasks.*.risks.*.controls.*.description' => ['required', 'string', 'max:5000'],
            'processes.*.tasks.*.risks.*.controls.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'processes.*.tasks.*.risks.*.controls.*.responsible_employee_id' => ['nullable', 'integer', 'exists:staff,id'],
            'processes.*.tasks.*.risks.*.controls.*.responsible_text' => ['nullable', 'string', 'max:2000'],
            'processes.*.tasks.*.risks.*.controls.*.status' => ['nullable', Rule::in(['pending', 'planned', 'in_progress', 'implemented', 'verified', 'ineffective', 'cancelled', 'overdue'])],
            'processes.*.tasks.*.risks.*.controls.*.priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'processes.*.tasks.*.risks.*.controls.*.planned_start_date' => ['nullable', 'date'],
            'processes.*.tasks.*.risks.*.controls.*.due_date' => ['nullable', 'date'],
            'processes.*.tasks.*.risks.*.controls.*.periodicity_type' => ['nullable', Rule::in(['once', 'per_event', 'daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual', 'custom'])],
            'processes.*.tasks.*.risks.*.controls.*.periodicity_value' => ['nullable', 'integer', 'min:1'],
            'processes.*.tasks.*.risks.*.controls.*.progress_percentage' => ['nullable', 'integer', 'between:0,100'],
            'processes.*.tasks.*.risks.*.controls.*.creates_program_action' => ['nullable', 'boolean'],
        ];
    }
}
