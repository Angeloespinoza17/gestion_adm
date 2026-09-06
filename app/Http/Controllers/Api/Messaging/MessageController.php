<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Services\Messaging\AuditService;
use App\Services\Messaging\MessagePresenter;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\MessagingBroadcaster;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    public function __construct(
        private MessageService $service,
        private AuditService $audit,
        private MessagePresenter $presenter,
        private MessagingBroadcaster $broadcaster,
    ) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $syncCursor = now()->subSecond();
        $limit = min(max($request->integer('limit', 40), 1), 100);
        $isIncrementalUpdate = $request->filled('updated_since');
        $messageQuery = $isIncrementalUpdate
            ? $conversation->messages()->withTrashed()->getQuery()
            : $conversation->messages()->getQuery();
        $query = $this->presenter->prepareQuery(
            $messageQuery,
            (int) $request->user()->id
        );
        if ($request->filled('before')) {
            $before = Message::query()
                ->when($isIncrementalUpdate, fn ($beforeQuery) => $beforeQuery->withTrashed())
                ->where('public_id', $request->string('before'))
                ->where('conversation_id', $conversation->id)
                ->firstOrFail();
            $query->where('id', '<', $before->id);
        }
        $afterId = $request->input('after_id', $request->input('since'));
        if ($afterId) {
            $since = Message::query()->where('public_id', $afterId)->where('conversation_id', $conversation->id)->firstOrFail();
            $query->where('id', '>', $since->id)->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }
        if ($isIncrementalUpdate) {
            $updatedSince = CarbonImmutable::parse($request->validate(['updated_since' => ['required', 'date']])['updated_since']);
            $query->where('updated_at', '>=', $updatedSince);
        }
        $page = $query->limit($limit + 1)->get();
        $hasMore = $page->count() > $limit;
        $pageItems = $page->take($limit)->values();
        $messages = $pageItems->reject(fn (Message $message) => $message->trashed())->values();
        $deleted = $pageItems->filter(fn (Message $message) => $message->trashed())->map(fn (Message $message) => [
            'public_id' => $message->public_id,
            'deleted_at' => $message->deleted_at,
        ])->values();
        $ids = $messages->pluck('id');
        MessageRecipient::query()->where('user_id', $request->user()->id)->whereIn('message_id', $ids)->whereNull('delivered_at')->update(['delivered_at' => now()]);

        return response()->json(['data' => $messages->map(fn ($message) => $this->presenter->present($message, $request->user()->id))->values(), 'deleted' => $deleted, 'before' => $pageItems->last()?->public_id, 'has_more' => $hasMore, 'sync_cursor' => $syncCursor->toIso8601String()]);
    }

    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('send', $conversation);

        return response()->json(['data' => $this->presenter->present($this->service->send($conversation, $request->user(), $request->validated()), $request->user()->id)], 201);
    }

    public function show(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        return response()->json(['data' => $this->presenter->present($message, $request->user()->id)]);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $this->authorize('update', $message);
        $data = $request->validate(['body' => ['required', 'string', 'max:'.config('messaging.messages.max_length')]]);
        DB::transaction(function () use ($message, $data, $request) {
            $message->versions()->create(['version_number' => $message->current_version + 1, 'subject' => $message->subject, 'body' => $data['body'], 'priority' => $message->priority, 'edited_by' => $request->user()->id, 'created_at' => now()]);
            $message->update(['body' => trim($data['body']), 'current_version' => $message->current_version + 1, 'edited_at' => now()]);
            $message->load('attachments');
            $message->update(['content_hash' => $this->service->hash($message)]);
            $this->audit->record('message_edited', $request->user()->id, $message->conversation_id, $message->id);
        });
        $message = $message->fresh();
        $this->broadcaster->messageUpdated($message, $request->user()->id);

        return response()->json(['data' => $this->presenter->present($message, $request->user()->id)]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $this->authorize('delete', $message);
        $conversation = $message->conversation;
        DB::transaction(function () use ($message, $conversation, $request) {
            $message->delete();
            if ($conversation->last_message_id === $message->id) {
                $previous = $conversation->messages()->latest('id')->first();
                $conversation->update(['last_message_id' => $previous?->id, 'last_message_at' => $previous?->sent_at]);
            }
            $this->audit->record('message_deleted', $request->user()->id, $message->conversation_id, $message->id);
        });
        $this->broadcaster->messageDeleted($conversation->fresh(), $message->public_id, $request->user()->id);

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
        $item = $message->reactions()->firstOrCreate(['user_id' => $request->user()->id, 'reaction' => $data['reaction']]);
        $message->touch();
        if ($item->wasRecentlyCreated) {
            $this->broadcaster->reactionUpdated($message, $request->user()->id, $data['reaction'], true);
        }

        return response()->json(['data' => ['message_id' => $message->public_id, 'reaction' => $data['reaction'], 'active' => true]]);
    }

    public function unreact(Request $request, Message $message, string $reaction): JsonResponse
    {
        $this->authorize('view', $message);
        abort_unless(in_array($reaction, config('messaging.reactions'), true), 422);
        $deleted = $message->reactions()->where('user_id', $request->user()->id)->where('reaction', $reaction)->delete();
        if ($deleted) {
            $message->touch();
            $this->broadcaster->reactionUpdated($message, $request->user()->id, $reaction, false);
        }

        return response()->json(['message' => 'Reacción eliminada.']);
    }
}
