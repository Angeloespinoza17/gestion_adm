<?php

namespace App\Http\Requests\LibroDigital;

use App\Http\Requests\LibroDigital\Concerns\ValidatesCurriculumEvidenceFiles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ActivateCurriculumImportRequest extends FormRequest
{
    use ValidatesCurriculumEvidenceFiles;

    protected function prepareForValidation(): void
    {
        if (($version = $this->versionFromIfMatch()) !== null) {
            $this->merge(['lock_version' => $version]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.curriculum.activate') ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            ...$this->curriculumEvidenceFileRules(),
            'status' => ['prohibited'],
            'catalog_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateCurriculumEvidenceFiles($validator);
    }

    private function versionFromIfMatch(): ?int
    {
        $header = trim((string) $this->header('If-Match'));
        if (! preg_match('/^(?:W\/)?"?(\d+)"?$/', $header, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
