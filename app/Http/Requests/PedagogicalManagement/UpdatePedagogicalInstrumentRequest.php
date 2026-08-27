<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePedagogicalInstrumentRequest extends FormRequest
{
    use InstrumentRules;

    public function authorize(): bool
    {
        $instrument = $this->route('instrument');

        return $instrument && $this->user()?->can('update', $instrument);
    }

    public function rules(): array
    {
        return $this->instrumentRules(true);
    }
}
