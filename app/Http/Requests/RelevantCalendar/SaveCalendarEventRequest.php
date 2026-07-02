<?php

namespace App\Http\Requests\RelevantCalendar;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCalendarEventRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'creation_mode' => $this->input('creation_mode', 'single'),
            'edit_scope' => $this->input('edit_scope', 'single'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creationMode = $this->input('creation_mode', 'single');
        $isRecurring = $creationMode === 'recurring';
        $isProcess = $creationMode === 'process';

        $priorityValues = array_column(CalendarEvent::PRIORITY_OPTIONS, 'value');
        $statusValues = array_column(CalendarEvent::STATUS_OPTIONS, 'value');
        $reminderValues = array_column(CalendarEvent::REMINDER_TYPE_OPTIONS, 'value');
        $staffUserExists = Rule::exists(User::class, 'id')->where(function ($query) {
            $query
                ->where('user_type', 'staff')
                ->whereNotNull('staff_id');
        });

        return [
            'creation_mode' => ['required', Rule::in(['single', 'recurring', 'process'])],
            'edit_scope' => ['nullable', Rule::in(['single', 'this_occurrence', 'future', 'all'])],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'process_type_id' => ['nullable', 'integer', Rule::exists('calendar_process_types', 'id')],
            'institution_id' => ['nullable', 'integer', Rule::exists('calendar_institutions', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'responsible_user_id' => ['nullable', 'integer', $staffUserExists],
            'start_date' => [$isProcess ? 'nullable' : 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'priority' => ['required', Rule::in($priorityValues)],
            'status' => ['required', Rule::in($statusValues)],
            'external_url' => ['nullable', 'url', 'max:255'],
            'internal_observations' => ['nullable', 'string'],
            'requires_submission' => ['sometimes', 'boolean'],
            'requires_payment' => ['sometimes', 'boolean'],
            'requires_signature' => ['sometimes', 'boolean'],
            'requires_review' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['integer', $staffUserExists],
            'informed_user_ids' => ['nullable', 'array'],
            'informed_user_ids.*' => ['integer', $staffUserExists],
            'reminders' => ['nullable', 'array'],
            'reminders.*.id' => ['nullable', 'integer'],
            'reminders.*.reminder_type' => ['required_with:reminders', Rule::in($reminderValues)],
            'reminders.*.days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reminders.*.reminder_date' => ['nullable', 'date'],
            'reminders.*.is_active' => ['sometimes', 'boolean'],

            'recurrence' => [$isRecurring ? 'required' : 'nullable', 'array'],
            'recurrence.mode' => [$isRecurring ? 'required' : 'nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly', 'custom'])],
            'recurrence.frequency' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence.interval' => ['nullable', 'integer', 'min:1', 'max:36'],
            'recurrence.weekdays' => ['nullable', 'array'],
            'recurrence.weekdays.*' => [Rule::in(array_column(CalendarEvent::WEEKDAY_OPTIONS, 'value'))],
            'recurrence.monthly_mode' => ['nullable', Rule::in(['day_of_month', 'last_business_day'])],
            'recurrence.day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'recurrence.ends_on' => ['nullable', 'date'],
            'recurrence.auto_generate' => ['sometimes', 'boolean'],

            'stages' => [$isProcess ? 'required' : 'nullable', 'array', $isProcess ? 'min:1' : 'nullable'],
            'stages.*.id' => ['nullable', 'integer'],
            'stages.*.title' => [$isProcess ? 'required' : 'nullable', 'string', 'max:191'],
            'stages.*.stage_key' => ['nullable', 'string', 'max:80'],
            'stages.*.description' => ['nullable', 'string'],
            'stages.*.responsible_user_id' => ['nullable', 'integer', $staffUserExists],
            'stages.*.start_date' => [$isProcess ? 'required' : 'nullable', 'date'],
            'stages.*.end_date' => ['nullable', 'date'],
            'stages.*.start_time' => ['nullable', 'date_format:H:i'],
            'stages.*.end_time' => ['nullable', 'date_format:H:i'],
            'stages.*.priority' => ['nullable', Rule::in($priorityValues)],
            'stages.*.status' => ['nullable', Rule::in($statusValues)],
            'stages.*.reminders' => ['nullable', 'array'],
            'stages.*.reminders.*.id' => ['nullable', 'integer'],
            'stages.*.reminders.*.reminder_type' => ['nullable', Rule::in($reminderValues)],
            'stages.*.reminders.*.days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'stages.*.reminders.*.reminder_date' => ['nullable', 'date'],
            'stages.*.reminders.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateStageRanges($validator);
            $this->validateReminderPayload($validator, $this->input('reminders', []), 'reminders');

            foreach ((array) $this->input('stages', []) as $index => $stage) {
                $this->validateReminderPayload(
                    $validator,
                    data_get($stage, 'reminders', []),
                    "stages.$index.reminders"
                );
            }
        });
    }

    private function validateStageRanges(Validator $validator): void
    {
        foreach ((array) $this->input('stages', []) as $index => $stage) {
            $startDate = data_get($stage, 'start_date');
            $endDate = data_get($stage, 'end_date');

            if (!$startDate || !$endDate) {
                continue;
            }

            if (strtotime((string) $endDate) < strtotime((string) $startDate)) {
                $validator->errors()->add(
                    "stages.$index.end_date",
                    'La fecha de término de la etapa debe ser mayor o igual a la fecha de inicio.'
                );
            }
        }
    }

    private function validateReminderPayload(Validator $validator, array $reminders, string $prefix): void
    {
        foreach ($reminders as $index => $reminder) {
            $type = data_get($reminder, 'reminder_type');

            if (!$type) {
                $validator->errors()->add(
                    "$prefix.$index.reminder_type",
                    'Debe seleccionar el tipo de recordatorio.'
                );
                continue;
            }

            if (in_array($type, ['before', 'after_overdue'], true) && data_get($reminder, 'days_before') === null) {
                $validator->errors()->add(
                    "$prefix.$index.days_before",
                    'Debe indicar la cantidad de días para este recordatorio.'
                );
            }

            if ($type === 'fixed_date' && empty(data_get($reminder, 'reminder_date'))) {
                $validator->errors()->add(
                    "$prefix.$index.reminder_date",
                    'Debe indicar una fecha fija para este recordatorio.'
                );
            }
        }
    }
}
