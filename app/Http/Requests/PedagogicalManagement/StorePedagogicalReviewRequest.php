<?php

namespace App\Http\Requests\PedagogicalManagement;

use App\Enums\PedagogicalManagement\ReviewDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePedagogicalReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $instrument = $this->route('instrument');

        return $instrument && $this->user()?->can('review', $instrument);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'coordinator_notes' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('decision'), [
                    ReviewDecision::ApprovedWithObservations->value,
                    ReviewDecision::RectificationRequested->value,
                ], true)),
                'nullable',
                'string',
                'max:10000',
            ],
            'ai_report_id' => ['nullable', 'uuid', 'exists:pedagogical_instrument_ai_reports,uuid'],
            'share_ai_report' => ['sometimes', 'boolean'],
            'guidance_document_ids' => ['sometimes', 'array', 'max:30'],
            'guidance_document_ids.*' => ['uuid', 'distinct', 'exists:pedagogical_guidance_documents,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'coordinator_notes.required' => 'Escribe las observaciones o los cambios que debe realizar el docente.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('share_ai_report')
                && $this->input('decision') !== ReviewDecision::RectificationRequested->value) {
                $validator->errors()->add(
                    'share_ai_report',
                    'El informe sólo puede enviarse al docente cuando se solicita una rectificación.',
                );
            }
        });
    }
}
