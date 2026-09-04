<?php

namespace App\Http\Requests\Orientation;

use App\Models\Orientation\OrientationEvidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrientationEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('orientation.manage_evidence');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'evidence_type' => ['required', Rule::in(OrientationEvidence::TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'occurred_on' => ['nullable', 'date'],
            'orientation_activity_id' => ['nullable', 'integer', 'exists:orientation_activities,id'],
            'file' => [
                'nullable',
                'required_without:external_url',
                'file',
                'max:30720',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,csv,mp4,mov',
            ],
            'external_url' => ['nullable', 'required_without:file', 'url:http,https', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $action = $this->route('action');
            $date = $this->input('occurred_on');
            $year = $action?->plan?->year;

            if ($date && $year && (int) date('Y', strtotime((string) $date)) !== (int) $year) {
                $validator->errors()->add('occurred_on', "La fecha debe pertenecer al año {$year} del plan.");
            }
        });
    }
}
