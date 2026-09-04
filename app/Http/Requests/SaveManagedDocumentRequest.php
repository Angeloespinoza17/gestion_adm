<?php

namespace App\Http\Requests;

use App\Models\ManagedDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveManagedDocumentRequest extends FormRequest
{
    public const MAX_FILE_SIZE_KILOBYTES = 25 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $booleans = [];

        foreach (['is_public', 'is_active'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                $booleans[$field] = $value;
            }
        }

        if ($booleans !== []) {
            $this->merge($booleans);
        }
    }

    public function rules(): array
    {
        $creating = $this->route('managedDocument') === null;

        return [
            'title' => ['required', 'string', 'max:191'],
            'category' => ['required', 'string', Rule::in(ManagedDocument::CATEGORIES)],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'version' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_public' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'file' => [
                $creating ? 'required' : 'nullable',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx'])
                    ->max(self::MAX_FILE_SIZE_KILOBYTES),
                'extensions:pdf,doc,docx,xls,xlsx',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.extensions' => 'El archivo debe tener extensión PDF, DOC, DOCX, XLS o XLSX.',
            'file.mimetypes' => 'El contenido del archivo no corresponde a un documento permitido.',
        ];
    }
}
