<?php

namespace App\Http\Requests;

use App\Models\SiteEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSiteEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteEventId = $this->route('siteEvent')?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => [
                'nullable',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('site_events', 'slug')->ignore($siteEventId),
            ],
            'summary' => ['nullable', 'string', 'max:700'],
            'body' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:191'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'header_image_url' => ['nullable', 'string', 'max:2048'],
            'hero_image_url' => ['nullable', 'string', 'max:2048'],
            'hero_image_alt' => ['nullable', 'string', 'max:191'],
            'highlights' => ['nullable', 'array', 'max:20'],
            'highlights.*' => ['nullable', 'string', 'max:300'],
            'schedule_items' => ['nullable', 'array', 'max:30'],
            'schedule_items.*.time' => ['nullable', 'string', 'max:80'],
            'schedule_items.*.title' => ['nullable', 'string', 'max:160'],
            'schedule_items.*.description' => ['nullable', 'string', 'max:500'],
            'gallery_intro' => ['nullable', 'string', 'max:255'],
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*.url' => ['nullable', 'string', 'max:2048'],
            'gallery_images.*.alt' => ['nullable', 'string', 'max:191'],
            'registration_enabled' => ['nullable', 'boolean'],
            'registration_title' => ['nullable', 'string', 'max:191'],
            'registration_button_label' => ['nullable', 'string', 'max:80'],
            'registration_url' => ['nullable', 'string', 'max:2048'],
            'organizer_name' => ['nullable', 'string', 'max:191'],
            'organizer_position' => ['nullable', 'string', 'max:191'],
            'organizer_description' => ['nullable', 'string', 'max:1000'],
            'organizer_email' => ['nullable', 'email', 'max:191'],
            'organizer_phone' => ['nullable', 'string', 'max:80'],
            'organizer_image_url' => ['nullable', 'string', 'max:2048'],
            'organizer_image_alt' => ['nullable', 'string', 'max:191'],
            'status' => ['required', Rule::in(SiteEvent::STATUSES)],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'ends_at.after_or_equal' => 'La fecha de término debe ser posterior o igual al inicio.',
            'organizer_email.email' => 'El correo del organizador no tiene un formato válido.',
        ];
    }
}
