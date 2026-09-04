<?php

namespace App\Http\Requests;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['active', 'featured', 'remove_image', 'authorization_confirmed'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($value !== null) {
                $normalized[$field] = $value;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'quote' => ['required', 'string', 'max:2000'],
            'author_name' => ['required', 'string', 'max:120'],
            'author_role' => ['nullable', 'string', 'max:160'],
            'external_image_url' => ['nullable', 'string', 'max:2048'],
            'image_alt' => ['nullable', 'string', 'max:191'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'authorization_confirmed' => [
                'nullable',
                'boolean',
                Rule::requiredIf(fn () => $this->input('status') === Testimonial::STATUS_PUBLISHED),
                function (string $attribute, $value, \Closure $fail): void {
                    if (
                        $this->input('status') === Testimonial::STATUS_PUBLISHED
                        && $value !== true
                    ) {
                        $fail('Debes confirmar la autorización antes de publicar el testimonio.');
                    }
                },
            ],
            'status' => ['required', Rule::in(Testimonial::STATUSES)],
            'active' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
