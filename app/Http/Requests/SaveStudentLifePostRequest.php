<?php

namespace App\Http\Requests;

use App\Models\StudentLifePost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudentLifePostRequest extends FormRequest
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

        foreach (['gallery_alts', 'remove_gallery_image_ids'] as $field) {
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
        $postId = $this->route('studentLifePost')?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => [
                'nullable',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('student_life_posts', 'slug')->ignore($postId),
            ],
            'category' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:700'],
            'body' => ['nullable', 'string'],
            'external_cover_image_url' => ['nullable', 'string', 'max:2048'],
            'cover_image_alt' => ['nullable', 'string', 'max:191'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_cover_image' => ['nullable', 'boolean'],
            'gallery' => ['nullable', 'array', 'max:12'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'gallery_alts' => ['nullable', 'array', 'max:12'],
            'gallery_alts.*' => ['nullable', 'string', 'max:191'],
            'remove_gallery_image_ids' => ['nullable', 'array', 'max:12'],
            'remove_gallery_image_ids.*' => ['integer', 'distinct', 'min:1'],
            'event_date' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'status' => ['required', Rule::in(StudentLifePost::STATUSES)],
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
            'gallery.max' => 'Puedes agregar hasta 12 imágenes por vez.',
        ];
    }
}
