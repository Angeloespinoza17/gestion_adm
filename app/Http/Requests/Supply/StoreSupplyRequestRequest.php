<?php

namespace App\Http\Requests\Supply;

use App\Models\Supply\SupplyItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplyRequestRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.supply_item_id' => [
                'nullable',
                'integer',
                Rule::exists('supply_items', 'id')->where('section', SupplyItem::SECTION_CLEANING),
            ],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.requested_quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'items.*.photo' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,image/x-heic,image/x-heif'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $line) {
                if (! empty($line['supply_item_id'])) {
                    continue;
                }

                if (trim((string) ($line['name'] ?? '')) === '') {
                    $validator->errors()->add("items.{$index}.name", 'Indica el nombre del producto agregado manualmente.');
                }
                if (trim((string) ($line['unit'] ?? '')) === '') {
                    $validator->errors()->add("items.{$index}.unit", 'Indica la unidad del producto agregado manualmente.');
                }
            }
        }];
    }
}
