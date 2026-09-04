<?php

namespace App\Http\Requests;

use App\Models\SiteInstallation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSiteInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['active', 'featured', 'remove_cover_image'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($value !== null) {
                $normalized[$field] = $value;
            }
        }

        foreach ([
            'features',
            'gallery_alts',
            'remove_gallery_image_ids',
            'gallery_order',
        ] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $normalized[$field] = $decoded;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        $installationId = $this->route('siteInstallation')?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => [
                'nullable',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('site_installations', 'slug')->ignore($installationId),
            ],
            'category' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:700'],
            'body' => ['nullable', 'string'],
            'location_label' => ['nullable', 'string', 'max:191'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'accessibility_notes' => ['nullable', 'string', 'max:1000'],
            'features' => ['nullable', 'array', 'max:12'],
            'features.*' => ['required', 'string', 'max:120', 'distinct'],
            'icon' => ['nullable', Rule::in(array_keys(SiteInstallation::ICONS))],
            'cover_image_alt' => ['nullable', 'string', 'max:191'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_cover_image' => ['nullable', 'boolean'],
            'gallery' => ['nullable', 'array', 'max:12'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'gallery_alts' => ['nullable', 'array', 'max:12'],
            'gallery_alts.*' => ['nullable', 'string', 'max:191'],
            'remove_gallery_image_ids' => ['nullable', 'array', 'max:12'],
            'remove_gallery_image_ids.*' => [
                'integer',
                'distinct',
                'min:1',
                'exists:site_installation_images,id',
            ],
            'gallery_order' => ['nullable', 'array', 'max:12'],
            'gallery_order.*' => [
                'integer',
                'distinct',
                'min:1',
                'exists:site_installation_images,id',
            ],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'status' => ['required', Rule::in(SiteInstallation::STATUSES)],
            'active' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'features.max' => 'Puedes registrar hasta 12 características por instalación.',
            'features.*.distinct' => 'Las características no pueden repetirse.',
            'gallery.max' => 'Puedes agregar hasta 12 imágenes por vez.',
        ];
    }
}
