<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class ReturnEarlyWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.withdrawals.manage')
            || $this->user()?->hasPermission('registrar_retiro_porteria')
            || false;
    }

    public function rules(): array
    {
        return [
            'returned_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
