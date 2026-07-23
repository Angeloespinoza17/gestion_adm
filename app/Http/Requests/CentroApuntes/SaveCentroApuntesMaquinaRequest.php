<?php

namespace App\Http\Requests\CentroApuntes;

use App\Http\Requests\CentroApuntes\Concerns\NormalizesNullableFields;
use App\Models\CentroApuntes\CentroApuntesMaquina;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCentroApuntesMaquinaRequest extends FormRequest
{
    use NormalizesNullableFields;

    protected function prepareForValidation(): void
    {
        $this->normalizeNullableFields(['brand', 'model', 'location', 'responsible_user_id', 'observations']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $machineId = $this->route('machine')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'internal_code' => ['required', 'string', 'max:80', Rule::unique('centro_apuntes_maquinas', 'internal_code')->ignore($machineId)],
            'type' => ['required', Rule::in(CentroApuntesMaquina::TYPE_OPTIONS)],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],
            'responsible_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('user_type', 'staff')->where('active', true))],
            'status' => ['required', Rule::in(CentroApuntesMaquina::STATUS_OPTIONS)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
