<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreWebAnalyticsPageViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $path = parse_url((string) $this->input('path', '/'), PHP_URL_PATH) ?: '/';

        $this->merge([
            'path' => Str::startsWith($path, '/') ? $path : '/'.$path,
            'title' => Str::limit(Str::squish(strip_tags((string) $this->input('title'))), 255, ''),
            'page_type' => Str::lower(trim((string) $this->input('page_type', 'page'))),
            'content_type' => Str::lower(trim((string) $this->input('content_type'))),
            'content_identifier' => trim((string) $this->input('content_identifier')),
            'content_slug' => Str::lower(trim((string) $this->input('content_slug'))),
            'referrer_host' => Str::lower(trim((string) $this->input('referrer_host'))),
            'campaign_source' => Str::limit(Str::squish((string) $this->input('campaign_source')), 120, ''),
            'campaign_medium' => Str::limit(Str::squish((string) $this->input('campaign_medium')), 80, ''),
            'campaign_name' => Str::limit(Str::squish((string) $this->input('campaign_name')), 191, ''),
        ]);
    }

    public function rules(): array
    {
        return [
            'visitor_id' => ['required', 'uuid'],
            'session_id' => ['required', 'uuid'],
            'path' => ['required', 'string', 'max:512', 'regex:/^\/(?!\/)/'],
            'title' => ['nullable', 'string', 'max:255'],
            'page_type' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'],
            'content_type' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'],
            'content_identifier' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'content_slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'referrer_host' => ['nullable', 'string', 'max:191', 'regex:/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)$/'],
            'campaign_source' => ['nullable', 'string', 'max:120'],
            'campaign_medium' => ['nullable', 'string', 'max:80'],
            'campaign_name' => ['nullable', 'string', 'max:191'],
            'viewport_width' => ['nullable', 'integer', 'between:240,10000'],
        ];
    }
}
