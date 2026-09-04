<?php

namespace App\Http\Requests\Supply;

use App\Models\Supply\SupplyItem;
use App\Services\Supply\SupplyRecipientService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplyDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => ['required', Rule::in(SupplyItem::sections())],
            'storeroom_id' => [
                Rule::requiredIf(fn (): bool => $this->input('section') === SupplyItem::SECTION_MAINTENANCE_STOREROOM),
                'nullable',
                'integer',
                Rule::exists('supply_storerooms', 'id')->where('active', true),
            ],
            'delivered_at' => ['required', 'date'],
            'recipient_staff_id' => ['required', 'integer', 'exists:staff,id'],
            'destination' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.supply_item_id' => ['required', 'integer', 'distinct', 'exists:supply_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('recipient_staff_id')) {
                return;
            }

            $recipientId = (int) $this->input('recipient_staff_id');
            if (! app(SupplyRecipientService::class)->isEligible($recipientId)) {
                $validator->errors()->add(
                    'recipient_staff_id',
                    'Selecciona un funcionario activo marcado para recibir OT de Mantención.',
                );
            }
        }];
    }
}
