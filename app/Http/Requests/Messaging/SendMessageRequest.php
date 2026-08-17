<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['body' => ['nullable', 'string', 'max:'.config('messaging.messages.max_length'), 'required_without:upload_tokens'], 'subject' => ['nullable', 'string', 'max:255'], 'priority' => ['sometimes', Rule::in(['normal', 'high', 'urgent'])], 'formal' => ['sometimes', 'boolean'], 'reply_to_id' => ['nullable', 'string', 'size:26'], 'requires_acknowledgement' => ['sometimes', 'boolean'], 'acknowledgement_due_at' => ['nullable', 'date', 'after:now'], 'acknowledgement_comment_required' => ['sometimes', 'boolean'], 'acknowledgement_user_ids' => ['sometimes', 'array'], 'acknowledgement_user_ids.*' => ['integer', 'distinct'], 'allow_replies' => ['sometimes', 'boolean'], 'upload_tokens' => ['sometimes', 'array', 'max:'.config('messaging.attachments.max_files')], 'upload_tokens.*' => ['string', 'size:26', 'distinct']];
    }
}
