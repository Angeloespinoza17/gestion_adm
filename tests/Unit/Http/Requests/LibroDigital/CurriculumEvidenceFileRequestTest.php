<?php

namespace Tests\Unit\Http\Requests\LibroDigital;

use App\Http\Requests\LibroDigital\ActivateCurriculumImportRequest;
use App\Http\Requests\LibroDigital\ValidateCurriculumImportRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;
use Tests\TestCase;

class CurriculumEvidenceFileRequestTest extends TestCase
{
    public function test_multisource_evidence_files_are_accepted_with_explicit_safe_keys(): void
    {
        foreach ($this->requestClasses() as $requestClass) {
            $validator = $this->evidenceValidator($requestClass, [
                'BCEP_2018' => UploadedFile::fake()->createWithContent('bcep.pdf', "%PDF-1.7\nsource"),
                'BASES.1B-6B' => UploadedFile::fake()->createWithContent('bases.html', '<!doctype html><html><body>Fuente</body></html>'),
                'BASES_7B-2M' => UploadedFile::fake()->createWithContent('bases.xhtml', '<?xml version="1.0"?><html xmlns="http://www.w3.org/1999/xhtml"></html>'),
                'MATRIZ_TP' => UploadedFile::fake()->createWithContent(
                    'matriz.xlsx',
                    (string) file_get_contents(resource_path('templates/libro-digital/plantilla-importacion-curriculo-nt1-4m.xlsx')),
                ),
            ]);

            $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
        }
    }

    public function test_positional_or_unsafe_source_keys_are_rejected(): void
    {
        foreach ($this->requestClasses() as $requestClass) {
            foreach ([0, 'source_lowercase', '../BCEP_2018'] as $sourceKey) {
                $validator = $this->evidenceValidator($requestClass, [
                    $sourceKey => UploadedFile::fake()->createWithContent('source.pdf', "%PDF-1.7\nsource"),
                ]);

                $this->assertTrue($validator->fails());
                $this->assertStringContainsString('source_key', $validator->errors()->first());
            }
        }
    }

    public function test_extension_must_match_real_mime_type_and_size_is_limited(): void
    {
        foreach ($this->requestClasses() as $requestClass) {
            $htmlContents = UploadedFile::fake()->createWithContent('source.html', '<!doctype html><html></html>');
            $disguised = $this->evidenceValidator($requestClass, [
                'SOURCE_PDF' => new UploadedFile(
                    (string) $htmlContents->getRealPath(),
                    'source.pdf',
                    'application/pdf',
                    UPLOAD_ERR_OK,
                    true,
                ),
            ]);
            $this->assertTrue($disguised->fails());
            $this->assertStringContainsString('MIME real', $disguised->errors()->first('evidence_files.SOURCE_PDF'));

            $oversized = $this->evidenceValidator($requestClass, [
                'SOURCE_PDF' => UploadedFile::fake()->create('source.pdf', 20_481, 'application/pdf'),
            ]);
            $this->assertTrue($oversized->fails());
            $this->assertTrue($oversized->errors()->has('evidence_files.SOURCE_PDF'));
        }
    }

    /** @return list<class-string<FormRequest>> */
    private function requestClasses(): array
    {
        return [
            ValidateCurriculumImportRequest::class,
            ActivateCurriculumImportRequest::class,
        ];
    }

    /** @param class-string<FormRequest> $requestClass @param array<int|string, UploadedFile> $files */
    private function evidenceValidator(string $requestClass, array $files): LaravelValidator
    {
        /** @var FormRequest $request */
        $request = $requestClass::create('/', 'POST', [], [], ['evidence_files' => $files]);
        $rules = Arr::only($request->rules(), ['evidence_files', 'evidence_files.*']);
        $validator = Validator::make($request->all(), $rules);
        $request->withValidator($validator);

        return $validator;
    }
}
