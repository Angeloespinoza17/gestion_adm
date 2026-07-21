<?php

namespace App\Http\Requests;

use App\Models\NewsPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveNewsPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $decoded = [];

        foreach ([
            'detail_categories',
            'toc_items',
            'feature_points',
            'comparison_cards',
            'key_principles',
            'future_trends',
            'tags',
        ] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $json = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded[$field] = $json;
            }
        }

        if ($decoded !== []) {
            $this->merge($decoded);
        }
    }

    public function rules(): array
    {
        $newsPostId = $this->route('newsPost')?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => [
                'nullable',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('news_posts', 'slug')->ignore($newsPostId),
            ],
            'excerpt' => ['nullable', 'string', 'max:700'],
            'body' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:120'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'author_role' => ['nullable', 'string', 'max:191'],
            'external_image_url' => ['nullable', 'string', 'max:2048'],
            'image_alt' => ['nullable', 'string', 'max:191'],
            'header_image_url' => ['nullable', 'string', 'max:2048'],
            'author_image_url' => ['nullable', 'string', 'max:2048'],
            'author_image_alt' => ['nullable', 'string', 'max:191'],
            'reading_minutes' => ['nullable', 'integer', 'min:1', 'max:999'],
            'comments_label' => ['nullable', 'string', 'max:80'],
            'detail_categories' => ['nullable', 'array', 'max:8'],
            'detail_categories.*' => ['nullable', 'string', 'max:80'],
            'toc_items' => ['nullable', 'array', 'max:20'],
            'toc_items.*.label' => ['nullable', 'string', 'max:120'],
            'toc_items.*.anchor' => ['nullable', 'string', 'max:80'],
            'quote_text' => ['nullable', 'string', 'max:1000'],
            'quote_author' => ['nullable', 'string', 'max:191'],
            'secondary_section_title' => ['nullable', 'string', 'max:191'],
            'secondary_image_url' => ['nullable', 'string', 'max:2048'],
            'secondary_image_alt' => ['nullable', 'string', 'max:191'],
            'secondary_image_caption' => ['nullable', 'string', 'max:255'],
            'secondary_image_position' => ['nullable', Rule::in(['left', 'right', 'full'])],
            'feature_points' => ['nullable', 'array', 'max:8'],
            'feature_points.*.icon' => ['nullable', 'string', 'max:80'],
            'feature_points.*.title' => ['nullable', 'string', 'max:160'],
            'feature_points.*.description' => ['nullable', 'string', 'max:500'],
            'comparison_cards' => ['nullable', 'array', 'max:4'],
            'comparison_cards.*.icon' => ['nullable', 'string', 'max:80'],
            'comparison_cards.*.title' => ['nullable', 'string', 'max:160'],
            'comparison_cards.*.items' => ['nullable', 'array', 'max:12'],
            'comparison_cards.*.items.*' => ['nullable', 'string', 'max:220'],
            'key_principles' => ['nullable', 'array', 'max:6'],
            'key_principles.*.number' => ['nullable', 'string', 'max:12'],
            'key_principles.*.title' => ['nullable', 'string', 'max:160'],
            'key_principles.*.description' => ['nullable', 'string', 'max:500'],
            'info_box_icon' => ['nullable', 'string', 'max:80'],
            'info_box_title' => ['nullable', 'string', 'max:191'],
            'info_box_text' => ['nullable', 'string', 'max:1000'],
            'future_trends' => ['nullable', 'array', 'max:6'],
            'future_trends.*.icon' => ['nullable', 'string', 'max:80'],
            'future_trends.*.title' => ['nullable', 'string', 'max:160'],
            'future_trends.*.description' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['nullable', 'string', 'max:80'],
            'share_enabled' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(NewsPost::STATUSES)],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
        ];
    }
}
