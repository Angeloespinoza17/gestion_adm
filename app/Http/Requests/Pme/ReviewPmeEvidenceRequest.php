<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPmeEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_status' => ['required', Rule::in(PmeCatalogService::EVIDENCE_STATES)],
            'review_comments' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
