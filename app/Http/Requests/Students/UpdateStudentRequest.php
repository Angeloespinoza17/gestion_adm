<?php

namespace App\Http\Requests\Students;

use App\Models\StudentProfile;
use App\Models\User;
use App\Support\DateInput;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['rut', 'guardian_rut', 'guardian_backup_rut', 'father_rut', 'mother_rut'] as $field) {
            if ($this->exists($field)) {
                $data[$field] = Rut::normalize($this->input($field));
            }
        }

        foreach ([
            'birthdate',
            'father_birthdate',
            'mother_birthdate',
            'baptism_date',
            'first_communion_date',
            'confirmation_date',
        ] as $field) {
            if ($this->exists($field)) {
                $data[$field] = DateInput::normalize($this->input($field));
            }
        }

        foreach (['email', 'guardian_email', 'guardian_backup_email', 'father_email', 'mother_email'] as $field) {
            if ($this->exists($field)) {
                $value = $this->input($field);
                $data[$field] = $value ? mb_strtolower(trim((string) $value)) : null;
            }
        }

        foreach ([
            'has_repeated_course',
            'has_internet',
            'has_computer',
            'has_judicial_process',
            'has_chronic_illness',
            'has_medication_allergies',
            'has_physical_restrictions',
        ] as $field) {
            if ($this->exists($field)) {
                $value = $this->input($field);
                $data[$field] = $value === null || $value === ''
                    ? null
                    : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        if ($this->exists('pickup_restriction')) {
            $data['pickup_restriction'] = filter_var($this->input('pickup_restriction'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        if ($this->exists('registered_name')) {
            $value = trim((string) $this->input('registered_name'));
            $data['registered_name'] = $value !== '' ? $value : null;
        }

        if ($this->exists('siblings_in_school')) {
            $data['siblings_in_school'] = $this->input('siblings_in_school') === '' ? null : $this->input('siblings_in_school');
        }

        if ($this->exists('account_active')) {
            $data['account_active'] = filter_var($this->input('account_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($this->exists('authorized_pickup_people')) {
            $data['authorized_pickup_people'] = collect($this->input('authorized_pickup_people', []))
                ->map(function ($person) {
                    return [
                        'name' => trim((string) ($person['name'] ?? '')),
                        'rut' => Rut::normalize($person['rut'] ?? null),
                        'relationship' => trim((string) ($person['relationship'] ?? '')),
                        'phone' => trim((string) ($person['phone'] ?? '')),
                    ];
                })
                ->filter(fn ($person) => $person['name'] !== '')
                ->values()
                ->all();
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $student = $this->route('studentProfile');
        $studentId = $student?->id;
        $userId = $student?->user?->id;

        return [
            'first_name' => ['sometimes', 'string', 'max:191'],
            'last_name' => ['sometimes', 'string', 'max:191'],
            'registered_name' => ['nullable', 'string', 'max:255'],
            'rut' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('student_profiles', 'rut')->ignore($studentId),
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                        $fail('El RUT ingresado no es válido.');
                    }
                },
            ],
            'birthdate' => ['nullable', 'date'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('student_profiles', 'email')->ignore($studentId),
                function ($attribute, $value, $fail) use ($userId) {
                    if (!$value) {
                        return;
                    }

                    $exists = User::query()
                        ->where('email', $value)
                        ->when($userId, fn ($query) => $query->where('id', '!=', $userId))
                        ->exists();

                    if ($exists) {
                        $fail('El correo indicado ya está asignado a otro usuario del sistema.');
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'general_status' => ['sometimes', Rule::in(array_column(StudentProfile::GENERAL_STATUS_OPTIONS, 'value'))],
            'observations' => ['nullable', 'string'],
            'pickup_restriction' => ['nullable', 'boolean'],
            'pickup_restriction_notes' => ['nullable', 'string'],
            'porter_alert_notes' => ['nullable', 'string'],
            'authorized_pickup_people' => ['nullable', 'array'],
            'authorized_pickup_people.*.name' => ['required_with:authorized_pickup_people', 'string', 'max:191'],
            'authorized_pickup_people.*.rut' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'authorized_pickup_people.*.relationship' => ['nullable', 'string', 'max:100'],
            'authorized_pickup_people.*.phone' => ['nullable', 'string', 'max:50'],
            'tardiness_semester_one_notes' => ['nullable', 'string'],
            'absence_notes' => ['nullable', 'string'],
            'guardian_name' => ['nullable', 'string', 'max:191'],
            'guardian_relationship' => ['nullable', 'string', 'max:100'],
            'guardian_role' => ['nullable', 'string', 'max:100'],
            'guardian_rut' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'guardian_address' => ['nullable', 'string', 'max:255'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_backup_name' => ['nullable', 'string', 'max:191'],
            'guardian_backup_relationship' => ['nullable', 'string', 'max:100'],
            'guardian_backup_role' => ['nullable', 'string', 'max:100'],
            'guardian_backup_rut' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'guardian_backup_address' => ['nullable', 'string', 'max:255'],
            'guardian_backup_phone' => ['nullable', 'string', 'max:50'],
            'guardian_backup_email' => ['nullable', 'email', 'max:255'],
            'lives_with' => ['nullable', 'string', 'max:100'],
            'siblings_in_school' => ['nullable', 'integer', 'min:0', 'max:50'],
            'father_name' => ['nullable', 'string', 'max:191'],
            'father_rut' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'father_nationality' => ['nullable', 'string', 'max:100'],
            'father_address' => ['nullable', 'string', 'max:255'],
            'father_email' => ['nullable', 'email', 'max:255'],
            'father_occupation' => ['nullable', 'string', 'max:150'],
            'father_phone' => ['nullable', 'string', 'max:50'],
            'father_birthdate' => ['nullable', 'date'],
            'father_education_level' => ['nullable', 'string', 'max:150'],
            'mother_name' => ['nullable', 'string', 'max:191'],
            'mother_rut' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'mother_nationality' => ['nullable', 'string', 'max:100'],
            'mother_address' => ['nullable', 'string', 'max:255'],
            'mother_email' => ['nullable', 'email', 'max:255'],
            'mother_occupation' => ['nullable', 'string', 'max:150'],
            'mother_phone' => ['nullable', 'string', 'max:50'],
            'mother_birthdate' => ['nullable', 'date'],
            'mother_education_level' => ['nullable', 'string', 'max:150'],
            'has_repeated_course' => ['nullable', 'boolean'],
            'has_internet' => ['nullable', 'boolean'],
            'has_computer' => ['nullable', 'boolean'],
            'health_insurance' => ['nullable', 'string', 'max:150'],
            'beneficiary_programs' => ['nullable', 'string'],
            'scholarships' => ['nullable', 'string'],
            'has_judicial_process' => ['nullable', 'boolean'],
            'has_chronic_illness' => ['nullable', 'boolean'],
            'chronic_illness_details' => ['nullable', 'string'],
            'has_medication_allergies' => ['nullable', 'boolean'],
            'medication_allergies_details' => ['nullable', 'string'],
            'has_physical_restrictions' => ['nullable', 'boolean'],
            'physical_restrictions_details' => ['nullable', 'string'],
            'baptism_date' => ['nullable', 'date'],
            'baptism_place' => ['nullable', 'string', 'max:191'],
            'first_communion_date' => ['nullable', 'date'],
            'first_communion_place' => ['nullable', 'string', 'max:191'],
            'confirmation_date' => ['nullable', 'date'],
            'confirmation_place' => ['nullable', 'string', 'max:191'],
            'account_active' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
