<?php

namespace App\Http\Requests\Library;

use App\Models\Library\BibliotecaObra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBibliotecaObraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $obraId = $this->route('obra')?->id;

        return [
            'material_type' => ['required', Rule::in(BibliotecaObra::MATERIAL_TYPES)],
            'title' => ['required', 'string', 'max:191'],
            'subtitle' => ['nullable', 'string', 'max:191'],
            'main_author' => ['required', 'string', 'max:191'],
            'secondary_authors' => ['nullable', 'array'],
            'secondary_authors.*' => ['string', 'max:191'],
            'publisher' => ['nullable', 'string', 'max:191'],
            'publication_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'isbn' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:120'],
            'subcategory' => ['nullable', 'string', 'max:120'],
            'genre' => ['nullable', 'string', 'max:120'],
            'recommended_level' => ['nullable', 'string', 'max:120'],
            'recommended_course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'language' => ['nullable', 'string', 'max:80'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'description' => ['nullable', 'string'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:80'],
            'cover_image_url' => ['nullable', 'string', 'max:2048'],
            'internal_code' => ['required', 'string', 'max:80', Rule::unique('biblioteca_obras', 'internal_code')->ignore($obraId)],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('biblioteca_obras', 'barcode')->ignore($obraId)],
            'physical_location' => ['nullable', 'string', 'max:120'],
            'shelf' => ['nullable', 'string', 'max:120'],
            'section' => ['nullable', 'string', 'max:120'],
            'general_status' => ['required', Rule::in(BibliotecaObra::STATUS_OPTIONS)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
