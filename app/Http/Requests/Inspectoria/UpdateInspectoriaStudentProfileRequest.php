<?php

namespace App\Http\Requests\Inspectoria;

use App\Support\DateInput;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInspectoriaStudentProfileRequest extends FormRequest
{
    private const NULLABLE_TEXT_FIELDS = [
        'registered_name',
        'gender',
        'nationality',
        'phone',
        'address',
        'commune',
        'emergency_contact_name',
        'emergency_contact_phone',
        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_address',
        'guardian_commune',
        'guardian_backup_name',
        'guardian_backup_relationship',
        'guardian_backup_phone',
        'guardian_backup_address',
        'guardian_backup_commune',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (self::NULLABLE_TEXT_FIELDS as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $normalized[$field] = $value === '' ? null : $value;
        }

        foreach (['email', 'guardian_email', 'guardian_backup_email'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $normalized[$field] = $value === '' ? null : mb_strtolower($value);
        }

        foreach (['guardian_rut', 'guardian_backup_rut'] as $field) {
            if ($this->exists($field)) {
                $normalized[$field] = Rut::normalize($this->input($field));
            }
        }

        if ($this->exists('birthdate')) {
            $normalized['birthdate'] = DateInput::normalize($this->input('birthdate'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'profile_updated_at' => ['required', 'date'],
            'registered_name' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('student_profiles', 'email')->ignore($student?->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'guardian_name' => ['nullable', 'string', 'max:191'],
            'guardian_relationship' => ['nullable', 'string', 'max:100'],
            'guardian_rut' => ['nullable', 'string', 'max:20', $this->validRutRule()],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_address' => ['nullable', 'string', 'max:255'],
            'guardian_commune' => ['nullable', 'string', 'max:100'],
            'guardian_backup_name' => ['nullable', 'string', 'max:191'],
            'guardian_backup_relationship' => ['nullable', 'string', 'max:100'],
            'guardian_backup_rut' => ['nullable', 'string', 'max:20', $this->validRutRule()],
            'guardian_backup_phone' => ['nullable', 'string', 'max:50'],
            'guardian_backup_email' => ['nullable', 'email', 'max:255'],
            'guardian_backup_address' => ['nullable', 'string', 'max:255'],
            'guardian_backup_commune' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function validRutRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value !== null && $value !== '' && ! Rut::isValid($value)) {
                $fail('El RUT ingresado no es válido.');
            }
        };
    }
}
