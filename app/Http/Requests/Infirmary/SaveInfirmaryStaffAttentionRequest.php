<?php

namespace App\Http\Requests\Infirmary;

use App\Models\Infirmary\InfirmaryAttention;

class SaveInfirmaryStaffAttentionRequest extends SaveInfirmaryAttentionRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'referred_by_staff_id' => null,
            'attention_duration_minutes' => null,
            'status' => 'abierta',
        ]);
    }

    protected function attentionSubjectType(): string
    {
        return InfirmaryAttention::SUBJECT_STAFF;
    }
}
