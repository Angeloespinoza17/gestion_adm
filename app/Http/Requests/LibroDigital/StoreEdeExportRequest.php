<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEdeExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.ede.export') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'book_id' => ['required', 'integer', 'exists:lcd_books,id'],
            'ede_version_id' => ['required', 'integer', 'exists:lcd_ede_versions,id'],
            'normative_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_regulatory_profiles,id'],
            'export_type' => ['sometimes', Rule::in(['full'])],
            // El proyector actual no aplica filtros parciales. Aceptarlos haría
            // que el alcance declarado difiriera de los registros proyectados.
            'from' => ['prohibited'],
            'to' => ['prohibited'],
            'scope' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.prohibited' => 'La exportación EDE parcial por fecha aún no está implementada de forma verificable.',
            'to.prohibited' => 'La exportación EDE parcial por fecha aún no está implementada de forma verificable.',
            'scope.prohibited' => 'El alcance EDE no puede ser definido libremente por el cliente.',
        ];
    }
}
