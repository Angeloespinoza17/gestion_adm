<?php

namespace App\Http\Requests\Spaces;

use App\Models\DependencyReservation;
use App\Support\DateInput;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDependencyReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => DateInput::normalize($this->input('start_date')),
            'end_date' => DateInput::normalize($this->input('end_date')),
            'repetition_until' => DateInput::normalize($this->input('repetition_until')),
            'estimated_attendees' => $this->filled('estimated_attendees') ? (int) $this->input('estimated_attendees') : null,
            'repetition_type' => $this->input('repetition_type') ?: 'none',
            'collaborator_staff_ids' => collect($this->input('collaborator_staff_ids', []))
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all(),
            'collaborator_external_emails' => collect($this->input('collaborator_external_emails', []))
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => mb_strtolower(trim((string) $value)))
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'maintenance_dependency_id' => ['required', 'integer', 'exists:maintenance_dependencies,id'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'activity' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date'],
            'end_time' => ['required', 'date_format:H:i'],
            'repetition_type' => ['required', Rule::in(DependencyReservation::REPETITION_TYPES)],
            'repetition_until' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
            'estimated_attendees' => ['nullable', 'integer', 'min:0'],
            'special_requirements' => ['nullable', 'string'],
            'collaborator_staff_ids' => ['nullable', 'array'],
            'collaborator_staff_ids.*' => ['integer', 'exists:staff,id'],
            'collaborator_external_emails' => ['nullable', 'array'],
            'collaborator_external_emails.*' => ['email', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $startsAt = $this->startsAt();
            $endsAt = $this->endsAt();
            $timezone = config('app.timezone');

            if (!$startsAt || !$endsAt) {
                return;
            }

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                $validator->errors()->add('end_time', 'La fecha/hora de término debe ser posterior al inicio.');
            }

            if (
                $this->input('repetition_type') !== 'none'
                && $this->filled('repetition_until')
                && Carbon::parse((string) $this->input('repetition_until'), $timezone)->lt($endsAt->copy()->startOfDay())
            ) {
                $validator->errors()->add('repetition_until', 'La fecha final de repetición debe ser igual o posterior al término.');
            }
        });
    }

    public function startsAt(): ?Carbon
    {
        if (!$this->filled('start_date') || !$this->filled('start_time')) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $this->input('start_date') . ' ' . $this->input('start_time'),
            config('app.timezone')
        );
    }

    public function endsAt(): ?Carbon
    {
        if (!$this->filled('end_date') || !$this->filled('end_time')) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $this->input('end_date') . ' ' . $this->input('end_time'),
            config('app.timezone')
        );
    }
}
