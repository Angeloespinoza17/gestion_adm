<?php

namespace App\Http\Requests\LibroDigital\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

trait ValidatesCurriculumEvidenceFiles
{
    /** @return array<string, array<int, string>> */
    protected function curriculumEvidenceFileRules(): array
    {
        return [
            'evidence_files' => ['sometimes', 'array', 'max:100'],
            'evidence_files.*' => ['required', 'file', 'max:20480'],
        ];
    }

    protected function validateCurriculumEvidenceFiles(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $evidences = data_get($this->all(), 'evidence_files', []);
            if (! is_array($evidences)) {
                return;
            }

            foreach ($evidences as $sourceKey => $evidence) {
                $field = 'evidence_files.'.$sourceKey;
                if (! is_string($sourceKey)
                    || ! preg_match('/\A[A-Z0-9][A-Z0-9._-]{0,99}\z/D', $sourceKey)) {
                    $validator->errors()->add(
                        $field,
                        'Cada evidencia debe usar una clave source_key explícita, canónica y segura (A-Z, 0-9, punto, guion o guion bajo).',
                    );

                    continue;
                }

                if (! $evidence instanceof UploadedFile || ! $evidence->isValid()) {
                    continue;
                }

                $extension = mb_strtolower($evidence->getClientOriginalExtension());
                $mimeType = mb_strtolower((string) $evidence->getMimeType());
                if (! $this->curriculumEvidenceMimeMatches($extension, $mimeType)) {
                    $validator->errors()->add(
                        $field,
                        'La evidencia debe ser PDF, HTML, XHTML o XLSX y su extensión debe coincidir con el tipo MIME real.',
                    );
                }
            }
        });
    }

    private function curriculumEvidenceMimeMatches(string $extension, string $mimeType): bool
    {
        $allowed = [
            'pdf' => ['application/pdf', 'application/x-pdf'],
            'html' => ['text/html'],
            'htm' => ['text/html'],
            'xhtml' => ['application/xhtml+xml', 'application/xml', 'text/xml', 'text/html'],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
        ];

        return isset($allowed[$extension]) && in_array($mimeType, $allowed[$extension], true);
    }
}
