<?php

namespace App\Http\Requests\Inspectoria;

use App\Models\Inspectoria\InspectoriaPickupRestriction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInspectoriaPickupRestrictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'restricted_person_name' => ['required', 'string', 'max:191'],
            'restricted_person_rut' => ['nullable', 'string', 'max:30'],
            'restricted_person_relationship' => ['nullable', 'string', 'max:100'],
            'restriction_type' => ['required', 'string', Rule::in(array_keys(InspectoriaPickupRestriction::TYPES))],
            'reason' => ['required', 'string', 'max:3000'],
            'legal_reference' => ['nullable', 'string', 'max:500'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'active' => ['required', 'boolean'],
        ];
    }
}
