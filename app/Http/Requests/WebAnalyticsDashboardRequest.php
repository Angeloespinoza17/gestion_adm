<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class WebAnalyticsDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from', now()->subDays(29)->toDateString()),
            'to' => $this->input('to', now()->toDateString()),
            'content_type' => $this->filled('content_type') ? strtolower(trim((string) $this->input('content_type'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'content_type' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['from', 'to'])) {
                return;
            }

            $days = Carbon::parse((string) $this->input('from'))
                ->diffInDays(Carbon::parse((string) $this->input('to')));

            if ($days > 365) {
                $validator->errors()->add('to', 'El período máximo de consulta es de 366 días.');
            }
        }];
    }
}
