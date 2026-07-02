<?php

namespace App\Http\Requests\CentroApuntes;

use App\Models\CentroApuntes\CentroApuntesAsignatura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCentroApuntesAsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subjectId = $this->route('subject')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:80', Rule::unique('centro_apuntes_asignaturas', 'code')->ignore($subjectId)],
            'area' => ['nullable', 'string', 'max:120'],
            'education_level' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(CentroApuntesAsignatura::STATUS_OPTIONS)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
