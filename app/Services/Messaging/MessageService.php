<?php

namespace App\Services\Messaging;

use App\Events\Messaging\MessageCreated;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageAttachment;
use App\Models\Messaging\TemporaryUpload;
use App\Models\User;
use App\Notifications\Messaging\NewMessageNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageService
{
    public function __construct(private AuditService $audit) {}

    public function send(Conversation $conversation, User $actor, array $data): Message
    {
        return DB::transaction(function () use ($conversation, $actor, $data) {
            $participant = $conversation->participants()->where('user_id', $actor->id)->whereNull('left_at')->lockForUpdate()->firstOrFail();
            abort_if($conversation->is_locked || ! $participant->can_write || ($conversation->only_admins_can_write && ! in_array($participant->role, ['owner', 'admin'], true)), 409, 'No es posible enviar en esta conversación.');
            if (! empty($data['reply_to_id'])) {
                $reply = Message::query()->where('public_id', $data['reply_to_id'])->where('conversation_id', $conversation->id)->firstOrFail();
                abort_if(! $reply->allow_replies, 409, 'Esta comunicación no permite respuestas.');
                $data['reply_to_id'] = $reply->id;
            }
            $recipients = $conversation->participants()->whereNull('left_at')->where('user_id', '!=', $actor->id)->with('user:id,name,email')->get();
            $ackIds = collect($data['acknowledgement_user_ids'] ?? [])->map(fn ($id) => (int) $id);
            $requiresAck = (bool) ($data['requires_acknowledgement'] ?? false);
            $message = Message::query()->create(['public_id' => (string) Str::ulid(), 'conversation_id' => $conversation->id, 'sender_id' => $actor->id, 'sender_display_name_snapshot' => $actor->name, 'kind' => ($data['formal'] ?? false) || $requiresAck ? 'notice' : 'chat', 'subject' => $data['subject'] ?? null, 'body' => trim((string) ($data['body'] ?? '')), 'priority' => $data['priority'] ?? 'normal', 'reply_to_id' => $data['reply_to_id'] ?? null, 'requires_acknowledgement' => $requiresAck, 'acknowledgement_due_at' => $requiresAck ? ($data['acknowledgement_due_at'] ?? null) : null, 'acknowledgement_comment_required' => $requiresAck && ($data['acknowledgement_comment_required'] ?? false), 'allow_replies' => $data['allow_replies'] ?? true, 'recipient_count' => $recipients->count(), 'sent_at' => now()]);
            foreach ($recipients as $recipient) {
                $required = $requiresAck && ($ackIds->isEmpty() || $ackIds->contains($recipient->user_id));
                $message->recipients()->create(['user_id' => $recipient->user_id, 'recipient_display_name_snapshot' => $recipient->user->name, 'recipient_reference_snapshot' => $recipient->user->email, 'acknowledgement_required' => $required]);
            }
            $uploads = TemporaryUpload::query()->where('user_id', $actor->id)->whereNull('consumed_at')->where('expires_at', '>', now())->whereIn('public_id', $data['upload_tokens'] ?? [])->lockForUpdate()->get();
            abort_if($uploads->count() !== count($data['upload_tokens'] ?? []), 409, 'Una carga temporal no es válida o ya fue utilizada.');
            foreach ($uploads as $upload) {
                $target = 'messaging/messages/'.$message->public_id.'/'.$upload->stored_name;
                Storage::disk($upload->disk)->move($upload->path, $target);
                MessageAttachment::query()->create(['public_id' => (string) Str::ulid(), 'message_id' => $message->id, 'uploaded_by' => $actor->id, 'disk' => $upload->disk, 'path' => $target, 'original_name' => $upload->original_name, 'stored_name' => $upload->stored_name, 'mime_type' => $upload->mime_type, 'size' => $upload->size, 'checksum_sha256' => $upload->checksum_sha256]);
                $upload->update(['consumed_at' => now()]);
            }
            $message->content_hash = $this->hash($message->load('attachments'));
            $message->save();
            $conversation->update(['last_message_id' => $message->id, 'last_message_at' => $message->sent_at]);
            $message->versions()->create(['version_number' => 1, 'subject' => $message->subject, 'body' => $message->body, 'priority' => $message->priority, 'content_hash' => $message->content_hash, 'edited_by' => $actor->id, 'created_at' => now()]);
            $this->audit->record('message_created', $actor->id, $conversation->id, $message->id);
            if ($requiresAck) {
                $this->audit->record('acknowledgement_requested', $actor->id, $conversation->id, $message->id);
            }
            DB::afterCommit(function () use ($message, $recipients) {
                $message->load('conversation');
                foreach ($recipients as $recipient) {
                    $recipient->user->notify(new NewMessageNotification($message));
                }
                if (config('messaging.realtime.enabled')) {
                    event(new MessageCreated($message));
                }
            });

            return $message->load(['sender:id,name,profile_photo_path', 'attachments', 'reactions']);
        });
    }

    public function hash(Message $message): string
    {
        $payload = ['public_id' => $message->public_id, 'subject' => $message->subject, 'body' => $message->body, 'priority' => $message->priority, 'version' => $message->current_version, 'sent_at' => $message->sent_at?->utc()->toIso8601String(), 'attachments' => $message->attachments->sortBy('public_id')->map(fn ($a) => [$a->public_id, $a->checksum_sha256])->values()->all()];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
