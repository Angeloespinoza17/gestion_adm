<?php

namespace App\Http\Requests\Staff\Permissions;

use App\Models\PermissionRequestReplacement;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPermissionRequestReplacementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        $items = collect($items ?? [])
            ->map(function ($item) {
                $item['start_datetime'] = !empty($item['start_datetime'])
                    ? Carbon::parse($item['start_datetime'])->format('Y-m-d H:i:s')
                    : null;
                $item['end_datetime'] = !empty($item['end_datetime'])
                    ? Carbon::parse($item['end_datetime'])->format('Y-m-d H:i:s')
                    : null;

                return $item;
            })
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'items' => ['present', 'array'],
            'items.*.replaced_staff_id' => ['required', 'integer', 'exists:staff,id'],
            'items.*.replacement_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'items.*.course_id' => ['nullable', 'integer'],
            'items.*.course_name' => ['nullable', 'string', 'max:191'],
            'items.*.subject_id' => ['nullable', 'integer'],
            'items.*.subject_name' => ['nullable', 'string', 'max:191'],
            'items.*.dependency_name' => ['nullable', 'string', 'max:191'],
            'items.*.schedule_detail' => ['nullable', 'string'],
            'items.*.start_datetime' => ['required', 'date'],
            'items.*.end_datetime' => ['required', 'date'],
            'items.*.status' => ['required', Rule::in(array_column(PermissionRequestReplacement::STATUS_OPTIONS, 'value'))],
            'items.*.observations' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (($this->input('items') ?? []) as $index => $item) {
                if (
                    !empty($item['start_datetime'])
                    && !empty($item['end_datetime'])
                    && strtotime((string) $item['end_datetime']) <= strtotime((string) $item['start_datetime'])
                ) {
                    $validator->errors()->add("items.$index.end_datetime", 'La fecha/hora de término debe ser posterior al inicio.');
                }
            }
        });
    }
}
