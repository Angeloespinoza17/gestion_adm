<?php

namespace App\Http\Requests\CentroApuntes;

use App\Http\Requests\CentroApuntes\Concerns\NormalizesNullableFields;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCentroApuntesSolicitudRequest extends FormRequest
{
    use NormalizesNullableFields;

    protected function prepareForValidation(): void
    {
        $this->normalizeNullableFields([
            'task_type_other',
            'requested_at',
            'instructions',
            'observations',
            'internal_observations',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_by_user_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('user_type', 'staff')->where('active', true))],
            'subject_id' => ['required', 'integer', 'exists:centro_apuntes_asignaturas,id'],
            'machine_id' => ['required', 'integer', 'exists:centro_apuntes_maquinas,id'],
            'task_type' => ['required', Rule::in(CentroApuntesSolicitud::TASK_TYPES)],
            'task_type_other' => ['nullable', 'required_if:task_type,otro', 'string', 'max:191'],
            'requested_at' => ['nullable', 'date'],
            'delivery_date' => ['required', 'date'],
            'sheet_count' => ['required', 'integer', 'min:1'],
            'copies_count' => ['required', 'integer', 'min:1'],
            'paper_size' => ['required', Rule::in(CentroApuntesSolicitud::PAPER_SIZES)],
            'priority' => ['required', Rule::in(CentroApuntesSolicitud::PRIORITY_OPTIONS)],
            'is_urgent' => ['nullable', 'boolean'],
            'is_immediate' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'internal_observations' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
