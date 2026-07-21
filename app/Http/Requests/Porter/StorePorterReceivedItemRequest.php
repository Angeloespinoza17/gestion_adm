<?php

namespace App\Http\Requests\Porter;

use App\Models\PorterReceivedItem;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePorterReceivedItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $receivedFromRut = $this->input('received_from_rut');
        $receivedFromRut = is_string($receivedFromRut) ? trim($receivedFromRut) : null;

        $this->merge([
            'received_from_rut' => $receivedFromRut === '' ? null : (Rut::normalize($receivedFromRut) ?: $receivedFromRut),
        ]);
    }

    public function rules(): array
    {
        return [
            'recipient_type' => ['required', Rule::in(array_column(PorterReceivedItem::RECIPIENT_TYPE_OPTIONS, 'value'))],
            'recipient_label' => ['nullable', 'string', 'max:191'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'received_from_name' => ['required', 'string', 'max:191'],
            'received_from_rut' => ['nullable', 'string', 'max:20'],
            'received_from_phone' => ['nullable', 'string', 'max:50'],
            'item_type' => ['required', Rule::in(array_column(PorterReceivedItem::ITEM_TYPE_OPTIONS, 'value'))],
            'description' => ['required', 'string'],
            'status' => ['nullable', Rule::in(array_column(PorterReceivedItem::STATUS_OPTIONS, 'value'))],
            'observations' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ];
    }
}
