<?php

namespace App\Http\Requests\Operational;

use App\Models\Operational\OperationalStaffLogEntry;
use App\Services\Operational\OperationalStaffLogbookAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOperationalStaffLogEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(OperationalStaffLogbookAccessService::class)->canView($user);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => is_string($this->input('search')) ? trim($this->input('search')) : $this->input('search'),
        ]);
    }

    public function rules(): array
    {
        $isSuperAdmin = (bool) $this->user()?->isSuperAdmin();

        return [
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', Rule::in(array_column(OperationalStaffLogEntry::CATEGORY_OPTIONS, 'value'))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'owner_user_id' => [Rule::prohibitedIf(! $isSuperAdmin), 'nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
