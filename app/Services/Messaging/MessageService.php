<?php

namespace App\Services\Messaging;

use App\Jobs\Messaging\StoreNewMessageNotifications;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageAttachment;
use App\Models\Messaging\TemporaryUpload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageService
{
    public function __construct(private AuditService $audit, private MessagingBroadcaster $broadcaster) {}

    public function send(Conversation $conversation, User $actor, array $data): Message
    {
        abort_unless($actor->canUseMessaging(), 403, 'La mensajería institucional está disponible exclusivamente para funcionarios.');

        return DB::transaction(function () use ($conversation, $actor, $data) {
            $participant = $conversation->participants()->where('user_id', $actor->id)->whereNull('left_at')->lockForUpdate()->firstOrFail();
            abort_if($conversation->is_locked || ! $participant->can_write || ($conversation->only_admins_can_write && ! in_array($participant->role, ['owner', 'admin'], true)), 409, 'No es posible enviar en esta conversación.');
            if (! empty($data['reply_to_id'])) {
                $reply = Message::query()->where('public_id', $data['reply_to_id'])->where('conversation_id', $conversation->id)->firstOrFail();
                abort_if(! $reply->allow_replies, 409, 'Esta comunicación no permite respuestas.');
                $data['reply_to_id'] = $reply->id;
            }
            $chunkSize = max(1, (int) config('messaging.announcements.chunk_size', 500));
            $recipientQuery = DB::table('conversation_participants as cp')
                ->join('users as u', 'u.id', '=', 'cp.user_id')
                ->where('cp.conversation_id', $conversation->id)
                ->whereNull('cp.left_at')
                ->whereIn('u.id', User::query()->messagingStaff()->select('id'))
                ->where('cp.user_id', '!=', $actor->id);
            $recipientCount = (clone $recipientQuery)->count('cp.id');
            abort_if($recipientCount === 0, 409, 'La conversación no tiene otros funcionarios habilitados.');
            $acknowledgementUserIds = [];
            foreach ($data['acknowledgement_user_ids'] ?? [] as $userId) {
                $acknowledgementUserIds[(int) $userId] = true;
            }
            $requiresAck = (bool) ($data['requires_acknowledgement'] ?? false);
            $message = Message::query()->create(['public_id' => (string) Str::ulid(), 'conversation_id' => $conversation->id, 'sender_id' => $actor->id, 'sender_display_name_snapshot' => $actor->name, 'kind' => ($data['formal'] ?? false) || $requiresAck ? 'notice' : 'chat', 'subject' => $data['subject'] ?? null, 'body' => trim((string) ($data['body'] ?? '')), 'priority' => $data['priority'] ?? 'normal', 'reply_to_id' => $data['reply_to_id'] ?? null, 'requires_acknowledgement' => $requiresAck, 'acknowledgement_due_at' => $requiresAck ? ($data['acknowledgement_due_at'] ?? null) : null, 'acknowledgement_comment_required' => $requiresAck && ($data['acknowledgement_comment_required'] ?? false), 'allow_replies' => $data['allow_replies'] ?? true, 'recipient_count' => $recipientCount, 'sent_at' => now()]);
            $recipientTimestamp = now();
            $recipientQuery
                ->select(['cp.user_id as user_id', 'u.name', 'u.email'])
                ->orderBy('cp.user_id')
                ->chunkById($chunkSize, function ($recipients) use ($message, $requiresAck, $acknowledgementUserIds, $recipientTimestamp): void {
                    $rows = $recipients->map(function ($recipient) use ($message, $requiresAck, $acknowledgementUserIds, $recipientTimestamp): array {
                        $userId = (int) $recipient->user_id;

                        return [
                            'message_id' => $message->id,
                            'user_id' => $userId,
                            'recipient_display_name_snapshot' => $recipient->name,
                            'recipient_reference_snapshot' => $recipient->email,
                            'acknowledgement_required' => $requiresAck && ($acknowledgementUserIds === [] || isset($acknowledgementUserIds[$userId])),
                            'created_at' => $recipientTimestamp,
                            'updated_at' => $recipientTimestamp,
                        ];
                    })->all();

                    DB::table('message_recipients')->insert($rows);

                    StoreNewMessageNotifications::dispatch(
                        $message->id,
                        (int) $recipients->first()->user_id,
                        (int) $recipients->last()->user_id,
                    )->onQueue('notifications')->afterCommit();
                }, 'cp.user_id', 'user_id');
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
            DB::afterCommit(function () use ($message, $actor) {
                $message->load('conversation');
                $this->broadcaster->messageCreated($message, $actor->id);
            });

            return $message->load(['sender:id,name,profile_photo_path', 'attachments']);
        });
    }

    public function hash(Message $message): string
    {
        $payload = ['public_id' => $message->public_id, 'subject' => $message->subject, 'body' => $message->body, 'priority' => $message->priority, 'version' => $message->current_version, 'sent_at' => $message->sent_at?->utc()->toIso8601String(), 'attachments' => $message->attachments->sortBy('public_id')->map(fn ($a) => [$a->public_id, $a->checksum_sha256])->values()->all()];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
