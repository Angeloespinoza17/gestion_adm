<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPmeEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pme_action_id' => ['nullable', 'integer', 'exists:pme_acciones,id'],
            'pme_activity_id' => ['nullable', 'integer', 'exists:pme_actividades,id'],
            'pme_milestone_id' => ['nullable', 'integer', 'exists:pme_hitos,id'],
            'pme_indicator_measurement_id' => ['nullable', 'integer', 'exists:pme_indicador_mediciones,id'],
            'pme_goal_measurement_id' => ['nullable', 'integer', 'exists:pme_medicion_metas_estrategicas,id'],
            'pme_reflective_monitoring_id' => ['nullable', 'integer', 'exists:pme_monitoreos_reflexivos,id'],
            'evidence_type' => ['required', Rule::in(PmeCatalogService::EVIDENCE_TYPES)],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'document' => ['required', 'file', 'max:15360'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
