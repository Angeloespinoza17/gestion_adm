<?php

namespace App\Http\Requests\Supply;

use App\Models\Supply\SupplyItem;
use App\Models\Supply\SupplyRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSupplyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'needed_by' => ['nullable', 'date'],
            'status' => ['required', Rule::in(SupplyRequest::statuses())],
            'review_notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['nullable', 'integer', 'distinct', 'exists:supply_request_items,id'],
            'items.*.supply_item_id' => [
                'nullable',
                'integer',
                Rule::exists('supply_items', 'id')->where('section', SupplyItem::SECTION_CLEANING),
            ],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.requested_quantity' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
            'items.*.final_quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'items.*.photo' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,image/x-heic,image/x-heif'],
        ];
    }
}
