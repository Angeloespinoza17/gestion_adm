<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebAnalyticsEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visit_id' => ['required', 'uuid'],
            'visitor_id' => ['required', 'uuid'],
            'session_id' => ['required', 'uuid'],
            'engaged_seconds' => ['required', 'integer', 'between:0,43200'],
            'max_scroll_depth' => ['required', 'integer', 'between:0,100'],
            'interaction_count' => ['required', 'integer', 'between:0,65535'],
        ];
    }
}
