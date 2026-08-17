<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SignSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.sign') ?? false;
    }

    public function rules(): array
    {
        return [
            'otp' => ['required', 'string', 'min:4', 'max:16'],
            'timestamp' => ['required', 'string', 'max:40'],
            'payload_hash' => ['sometimes', 'nullable', 'string', 'size:64'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $value = (string) $this->input('timestamp');
            if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
                $validator->errors()->add('timestamp', 'El timestamp debe ser RFC3339 e incluir zona horaria.');
            }
        }];
    }
}
