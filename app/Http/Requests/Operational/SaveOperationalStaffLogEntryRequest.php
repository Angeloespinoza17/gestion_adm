<?php

namespace App\Http\Requests\Operational;

use App\Models\Operational\OperationalStaffLogEntry;
use App\Services\Operational\OperationalStaffLogbookAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOperationalStaffLogEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(OperationalStaffLogbookAccessService::class)->canCreate($user);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
            'details' => is_string($this->input('details')) ? trim($this->input('details')) : $this->input('details'),
            'custom_category' => is_string($this->input('custom_category')) ? trim($this->input('custom_category')) : $this->input('custom_category'),
        ]);
    }

    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date', 'after_or_equal:2000-01-01 00:00:00', 'before_or_equal:now'],
            'category' => ['required', Rule::in(array_column(OperationalStaffLogEntry::CATEGORY_OPTIONS, 'value'))],
            'custom_category' => ['nullable', 'required_if:category,other', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:180'],
            'details' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'occurred_at.before_or_equal' => 'La fecha y hora no pueden estar en el futuro.',
            'custom_category.required_if' => 'Indica el nombre de la categoría.',
            'title.required' => 'Escribe un título breve para el registro.',
            'details.required' => 'Describe el antecedente relevante para tu bitácora.',
        ];
    }
}
