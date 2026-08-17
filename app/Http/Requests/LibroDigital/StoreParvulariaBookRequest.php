<?php

namespace App\Http\Requests\LibroDigital;

class StoreParvulariaBookRequest extends StoreBookRequest
{
    public function authorize(): bool
    {
        return parent::authorize()
            && ($this->user()?->hasPermission('libro_digital.parvularia.manage') ?? false);
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            // Un libro parvulario exige que el perfil aplicable sea una decisión
            // explícita y versionada; nunca se infiere silenciosamente el perfil
            // regulatorio general.
            'normative_profile_id' => ['required', 'integer', 'exists:lcd_regulatory_profiles,id'],
            'regulatory_profile_id' => ['prohibited'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['modality' => 'parvularia']);
    }
}
