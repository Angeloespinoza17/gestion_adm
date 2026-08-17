<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Services\Messaging\AuditService;
use App\Services\Messaging\MessageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    public function __construct(private MessageService $service, private AuditService $audit) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $syncCursor = now()->subSecond();
        $query = $conversation->messages()->with(['sender:id,name,profile_photo_path', 'replyTo:id,public_id,body,sender_display_name_snapshot', 'attachments', 'reactions', 'recipients.user:id,name'])->whereNull('deleted_at')->orderByDesc('id');
        if ($request->filled('before')) {
            $before = Message::query()->where('public_id', $request->string('before'))->where('conversation_id', $conversation->id)->firstOrFail();
            $query->where('id', '<', $before->id);
        }
        if ($request->filled('since')) {
            $since = Message::query()->where('public_id', $request->string('since'))->where('conversation_id', $conversation->id)->firstOrFail();
            $query->where('id', '>', $since->id)->orderBy('id');
        }
        if ($request->filled('updated_since')) {
            $updatedSince = CarbonImmutable::parse($request->validate(['updated_since' => ['required', 'date']])['updated_since']);
            $query->where('updated_at', '>=', $updatedSince);
        }
        $messages = $query->limit(min(max($request->integer('limit', 40), 1), 100))->get();
        $ids = $messages->pluck('id');
        $conversation->messages()->whereIn('id', $ids)->whereHas('recipients', fn ($q) => $q->where('user_id', $request->user()->id))->each(fn ($message) => $message->recipients()->where('user_id', $request->user()->id)->whereNull('delivered_at')->update(['delivered_at' => now()]));

        return response()->json(['data' => $messages->map(fn ($m) => $this->present($m, $request->user()->id))->values(), 'before' => $messages->last()?->public_id, 'sync_cursor' => $syncCursor->toIso8601String()]);
    }

    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('send', $conversation);

        return response()->json(['data' => $this->present($this->service->send($conversation, $request->user(), $request->validated()), $request->user()->id)], 201);
    }

    public function show(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        return response()->json(['data' => $this->present($message->load(['sender:id,name,profile_photo_path', 'attachments', 'reactions', 'replyTo', 'recipients.user:id,name']), $request->user()->id)]);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $this->authorize('update', $message);
        $data = $request->validate(['body' => ['required', 'string', 'max:'.config('messaging.messages.max_length')]]);
        $message->versions()->create(['version_number' => $message->current_version + 1, 'subject' => $message->subject, 'body' => $data['body'], 'priority' => $message->priority, 'edited_by' => $request->user()->id, 'created_at' => now()]);
        $message->update(['body' => trim($data['body']), 'current_version' => $message->current_version + 1, 'edited_at' => now()]);
        $message->load('attachments');
        $message->update(['content_hash' => $this->service->hash($message)]);
        $this->audit->record('message_edited', $request->user()->id, $message->conversation_id, $message->id);

        return response()->json(['data' => $this->present($message->fresh(['sender', 'attachments', 'reactions']), $request->user()->id)]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $this->authorize('delete', $message);
        $message->delete();
        $this->audit->record('message_deleted', $request->user()->id, $message->conversation_id, $message->id);

        return response()->json(['message' => 'Mensaje eliminado.']);
    }

    public function supersede(SendMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);
        $this->authorize('send', $message->conversation);
        abort_unless($message->sender_id === $request->user()->id && $message->requires_acknowledgement, 403);
        $replacement = $this->service->send($message->conversation, $request->user(), array_merge($request->validated(), [
            'formal' => true,
            'requires_acknowledgement' => true,
        ]));
        $replacement->update(['supersedes_message_id' => $message->id]);
        $this->audit->record('message_superseded', $request->user()->id, $message->conversation_id, $replacement->id, null, ['original_message_id' => $message->public_id]);

        return response()->json(['data' => ['public_id' => $replacement->public_id, 'supersedes_message_id' => $message->public_id]], 201);
    }

    public function react(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);
        $data = $request->validate(['reaction' => ['required', 'string', Rule::in(config('messaging.reactions'))]]);
        $message->reactions()->firstOrCreate(['user_id' => $request->user()->id, 'reaction' => $data['reaction']]);
        $message->touch();

        return response()->json(['data' => $message->reactions()->get()]);
    }

    public function unreact(Request $request, Message $message, string $reaction): JsonResponse
    {
        $this->authorize('view', $message);
        abort_unless(in_array($reaction, config('messaging.reactions'), true), 422);
        $message->reactions()->where('user_id', $request->user()->id)->where('reaction', $reaction)->delete();
        $message->touch();

        return response()->json(['message' => 'Reacción eliminada.']);
    }

    private function present(Message $m, int $viewerId): array
    {
        $recipients = $m->relationLoaded('recipients') ? $m->recipients : $m->recipients()->with('user:id,name')->get();
        $recipient = $recipients->firstWhere('user_id', $viewerId);
        $acknowledgementRecipients = $recipients->where('acknowledgement_required', true);
        $pendingAcknowledgements = $acknowledgementRecipients->whereNull('acknowledged_at')->whereNull('waived_at');
        $isOverdue = $m->acknowledgement_due_at?->isPast() ?? false;
        $acknowledgementSummary = $m->requires_acknowledgement ? [
            'total' => $acknowledgementRecipients->count(),
            'acknowledged' => $acknowledgementRecipients->whereNotNull('acknowledged_at')->count(),
            'pending' => $isOverdue ? 0 : $pendingAcknowledgements->count(),
            'overdue' => $isOverdue ? $pendingAcknowledgements->count() : 0,
            'waived' => $acknowledgementRecipients->whereNotNull('waived_at')->count(),
            'acknowledged_by' => $m->sender_id === $viewerId
                ? $acknowledgementRecipients->whereNotNull('acknowledged_at')->sortByDesc('acknowledged_at')->take(5)->map(fn ($item) => [
                    'name' => $item->acknowledged_by_name_snapshot ?: $item->user?->name ?: $item->recipient_display_name_snapshot,
                    'acknowledged_at' => $item->acknowledged_at,
                ])->values()
                : [],
        ] : null;

        return ['public_id' => $m->public_id, 'sender_id' => $m->sender_id, 'sender' => ['name' => $m->sender?->name ?: $m->sender_display_name_snapshot, 'photo' => $m->sender?->profile_photo_url], 'kind' => $m->kind, 'subject' => $m->subject, 'body' => $m->body, 'priority' => $m->priority, 'reply_to' => $m->replyTo ? ['public_id' => $m->replyTo->public_id, 'body' => $m->replyTo->body, 'sender' => $m->replyTo->sender_display_name_snapshot] : null, 'requires_acknowledgement' => $m->requires_acknowledgement, 'acknowledgement_due_at' => $m->acknowledgement_due_at, 'acknowledgement_comment_required' => $m->acknowledgement_comment_required, 'acknowledgement_status' => $recipient?->acknowledgementStatus() ?? 'not_requested', 'acknowledgement_summary' => $acknowledgementSummary, 'content_hash' => $m->content_hash, 'sent_at' => $m->sent_at, 'edited_at' => $m->edited_at, 'attachments' => $m->attachments->map(fn ($a) => ['public_id' => $a->public_id, 'name' => $a->original_name, 'mime_type' => $a->mime_type, 'size' => $a->size, 'download_url' => '/api/messaging/attachments/'.$a->public_id]), 'reactions' => $m->reactions->groupBy('reaction')->map(fn ($x, $reaction) => ['reaction' => $reaction, 'count' => $x->count(), 'mine' => $x->contains('user_id', $viewerId)])->values()];
    }
}
