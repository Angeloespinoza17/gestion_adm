<?php

namespace App\Http\Requests\StudentHealth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudentMedicalLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:1500'],
            'is_permanent' => ['required', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif', 'max:15360'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('is_permanent') && ! $this->filled('ends_on')) {
                    $validator->errors()->add('ends_on', 'La fecha de término es obligatoria cuando la licencia no es permanente.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'starts_on.required' => 'Ingresa la fecha de inicio.',
            'ends_on.after_or_equal' => 'La fecha de término no puede ser anterior a la fecha de inicio.',
            'reason.required' => 'Ingresa el motivo de la licencia o certificado.',
            'reason.max' => 'El motivo no puede superar los 1500 caracteres.',
            'is_permanent.required' => 'Indica si corresponde a una condición permanente.',
            'attachment.file' => 'El respaldo adjunto no es un archivo válido.',
            'attachment.mimes' => 'El respaldo debe ser PDF o una imagen JPG, PNG, WEBP, HEIC o HEIF.',
            'attachment.max' => 'El respaldo no puede superar los 15 MB.',
        ];
    }
}
