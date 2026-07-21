<?php

namespace App\Http\Requests\InternalCommunications;

use App\Models\InternalCommunications\InternalAnnouncement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInternalAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $roleIds = $this->input('role_ids');

        if (is_string($roleIds)) {
            $decoded = json_decode($roleIds, true);
            $roleIds = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : array_filter(explode(',', $roleIds));
        }

        if (is_array($roleIds)) {
            $this->merge([
                'role_ids' => collect($roleIds)
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['required', Rule::in(InternalAnnouncement::PRIORITIES)],
            'status' => ['required', Rule::in(InternalAnnouncement::STATUSES)],
            'pinned' => ['nullable', 'boolean'],
            'audience_all' => ['nullable', 'boolean'],
            'requires_ack' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('audience_all')) {
                return;
            }

            if (count($this->input('role_ids', [])) === 0) {
                $validator->errors()->add('role_ids', 'Selecciona al menos un rol destinatario o marca la comunicacion para todos.');
            }

            if ($this->filled('published_at') && $this->filled('expires_at')) {
                $publishedAt = strtotime((string) $this->input('published_at'));
                $expiresAt = strtotime((string) $this->input('expires_at'));

                if ($publishedAt && $expiresAt && $expiresAt < $publishedAt) {
                    $validator->errors()->add('expires_at', 'La fecha de vencimiento debe ser posterior a la publicacion.');
                }
            }
        });
    }
}
