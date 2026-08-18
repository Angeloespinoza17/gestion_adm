<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\CreateConversationRequest;
use App\Models\Messaging\Conversation;
use App\Models\User;
use App\Services\Messaging\AuditService;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\MessagingBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function __construct(private ConversationService $service, private AuditService $audit, private MessagingBroadcaster $broadcaster) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Conversation::query()->visibleTo($user)
            ->with(['lastMessage.sender:id,name', 'participants' => fn ($q) => $q->whereNull('left_at')->with('user:id,name,profile_photo_path')])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereHas('recipients', fn ($r) => $r->where('user_id', $user->id)->whereNull('read_at'))])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($x) => $x->where('title', 'like', '%'.trim($request->string('search')).'%')->orWhereHas('participants.user', fn ($u) => $u->where('name', 'like', '%'.trim($request->string('search')).'%'))))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->boolean('unread'), fn ($q) => $q->whereHas('messages.recipients', fn ($r) => $r->where('user_id', $user->id)->whereNull('read_at')))
            ->orderByDesc('last_message_at')->orderByDesc('id');
        $page = $query->cursorPaginate(min(max($request->integer('limit', 30), 1), 50));

        return response()->json(['data' => collect($page->items())->map(fn ($c) => $this->present($c, $user))->values(), 'next_cursor' => $page->nextCursor()?->encode()]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return response()->json(['data' => $this->present($conversation->load(['participants.user:id,name,email,profile_photo_path', 'lastMessage.sender:id,name']), $request->user(), true)]);
    }

    public function direct(CreateConversationRequest $request): JsonResponse
    {
        $conversation = $this->service->direct($request->user(), User::query()->findOrFail($request->integer('user_id')));

        return response()->json(['data' => $this->present($conversation->load('participants.user:id,name,profile_photo_path'), $request->user(), true)], 201);
    }

    public function group(CreateConversationRequest $request): JsonResponse
    {
        $request->validate(['title' => ['required', 'string', 'max:255'], 'user_ids' => ['required', 'array', 'min:1']]);
        $conversation = $this->service->group($request->user(), $request->validated());

        return response()->json(['data' => $this->present($conversation->load('participants.user:id,name,profile_photo_path'), $request->user(), true)], 201);
    }

    public function announcement(CreateConversationRequest $request): JsonResponse
    {
        $request->validate(['title' => ['required', 'string', 'max:255']]);
        $conversation = $this->service->announcement($request->user(), $request->validated());

        return response()->json(['data' => $this->present($conversation->load('participants.user:id,name,profile_photo_path'), $request->user(), true)], 201);
    }

    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('manage', $conversation);
        abort_if($conversation->type === 'direct', 409, 'Una conversación directa no puede modificarse.');
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'only_admins_can_write' => ['sometimes', 'boolean']]);
        $conversation->update($data);
        $this->broadcaster->conversationChanged($conversation, 'conversation_updated', ['actor_id' => $request->user()->id]);

        return response()->json(['data' => $this->present($conversation->fresh('participants.user:id,name,profile_photo_path'), $request->user(), true)]);
    }

    public function preference(Request $request, Conversation $conversation, string $preference): JsonResponse
    {
        $this->authorize('view', $conversation);
        abort_unless(in_array($preference, ['archive', 'pin', 'mute'], true), 404);
        $participant = $conversation->participants()->where('user_id', $request->user()->id)->whereNull('left_at')->firstOrFail();
        $column = ['archive' => 'archived_at', 'pin' => 'pinned_at', 'mute' => 'muted_until'][$preference];
        $participant->update([$column => $request->isMethod('delete') ? null : ($preference === 'mute' ? now()->addDays(min($request->integer('days', 1), 365)) : now())]);
        $this->broadcaster->conversationChanged($conversation, 'preference_updated', ['actor_id' => $request->user()->id, 'preference' => $preference], [$request->user()->id]);

        return response()->json(['message' => 'Preferencia actualizada.']);
    }

    public function lock(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('manage', $conversation);
        $locked = ! $request->isMethod('delete');
        $conversation->update(['is_locked' => $locked]);
        $this->audit->record($locked ? 'conversation_locked' : 'conversation_unlocked', $request->user()->id, $conversation->id);
        $this->broadcaster->conversationChanged($conversation, $locked ? 'conversation_locked' : 'conversation_unlocked', ['actor_id' => $request->user()->id, 'is_locked' => $locked]);

        return response()->json(['data' => ['is_locked' => $locked]]);
    }

    public function participants(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return response()->json(['data' => $conversation->participants()->with('user:id,name,email,profile_photo_path')->get()]);
    }

    public function addParticipant(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('manage', $conversation);
        abort_if($conversation->type === 'direct', 409);
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1'], 'user_ids.*' => ['integer', 'distinct', 'exists:users,id']]);
        foreach (User::query()->where('active', true)->whereIn('id', $data['user_ids'])->get() as $user) {
            $conversation->participants()->updateOrCreate(['user_id' => $user->id], ['role' => 'member', 'can_write' => true, 'joined_at' => now(), 'left_at' => null]);
            $this->audit->record('participant_added', $request->user()->id, $conversation->id, null, $user->id);
        }
        $this->broadcaster->conversationChanged($conversation, 'participants_updated', ['actor_id' => $request->user()->id]);

        return $this->participants($request, $conversation);
    }

    public function removeParticipant(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $this->authorize('manage', $conversation);
        abort_if($conversation->type === 'direct', 409);
        $participant = $conversation->participants()->where('user_id', $user->id)->whereNull('left_at')->firstOrFail();
        abort_if($participant->role === 'owner', 409, 'Transfiere la propiedad antes de retirar al propietario.');
        $recipientIds = $conversation->activeParticipants()->pluck('user_id')->push($user->id)->unique()->map(fn ($id) => (int) $id)->all();
        $participant->update(['left_at' => now()]);
        $this->audit->record('participant_removed', $request->user()->id, $conversation->id, null, $user->id);
        $this->broadcaster->conversationChanged($conversation, 'participant_removed', ['actor_id' => $request->user()->id, 'removed_user_id' => $user->id], $recipientIds);

        return response()->json(['message' => 'Participante retirado.']);
    }

    public function updateParticipant(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $this->authorize('manage', $conversation);
        $data = $request->validate(['role' => ['sometimes', 'in:admin,member'], 'can_write' => ['sometimes', 'boolean']]);
        $participant = $conversation->participants()->where('user_id', $user->id)->whereNull('left_at')->firstOrFail();
        abort_if($participant->role === 'owner', 409, 'La propiedad se modifica mediante transferencia.');
        $participant->update($data);
        $this->broadcaster->conversationChanged($conversation, 'participants_updated', ['actor_id' => $request->user()->id, 'updated_user_id' => $user->id]);

        return response()->json(['message' => 'Participante actualizado.']);
    }

    public function transferOwnership(Request $request, Conversation $conversation): JsonResponse
    {
        $owner = $conversation->participants()->where('user_id', $request->user()->id)->where('role', 'owner')->whereNull('left_at')->first();
        abort_unless($owner, 403);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $next = $conversation->participants()->where('user_id', $data['user_id'])->whereNull('left_at')->firstOrFail();
        DB::transaction(function () use ($owner, $next) {
            $owner->update(['role' => 'admin']);
            $next->update(['role' => 'owner']);
        });
        $this->broadcaster->conversationChanged($conversation, 'ownership_transferred', ['actor_id' => $request->user()->id, 'owner_user_id' => $next->user_id]);

        return response()->json(['message' => 'Propiedad transferida.']);
    }

    public function leave(Request $request, Conversation $conversation): JsonResponse
    {
        abort_if($conversation->type === 'direct', 409, 'No puedes abandonar una conversación directa.');
        $participant = $conversation->participants()->where('user_id', $request->user()->id)->whereNull('left_at')->firstOrFail();
        abort_if($participant->role === 'owner', 409, 'Transfiere la propiedad antes de abandonar el grupo.');
        $recipientIds = $conversation->activeParticipants()->pluck('user_id')->push($request->user()->id)->unique()->map(fn ($id) => (int) $id)->all();
        $participant->update(['left_at' => now()]);
        $this->audit->record('participant_removed', $request->user()->id, $conversation->id, null, $request->user()->id, ['left_voluntarily' => true]);
        $this->broadcaster->conversationChanged($conversation, 'participant_removed', ['actor_id' => $request->user()->id, 'removed_user_id' => $request->user()->id], $recipientIds);

        return response()->json(['message' => 'Abandonaste la conversación.']);
    }

    private function present(Conversation $conversation, User $viewer, bool $detail = false): array
    {
        $participant = $conversation->participants->firstWhere('user_id', $viewer->id);
        $others = $conversation->participants->where('user_id', '!=', $viewer->id)->values();
        $title = $conversation->title ?: ($others->first()?->user?->name ?? 'Conversación');

        return ['public_id' => $conversation->public_id, 'type' => $conversation->type, 'title' => $title, 'description' => $conversation->description, 'is_locked' => $conversation->is_locked, 'only_admins_can_write' => $conversation->only_admins_can_write, 'last_message_at' => $conversation->last_message_at, 'last_message' => $conversation->lastMessage ? ['public_id' => $conversation->lastMessage->public_id, 'body' => $conversation->lastMessage->body, 'subject' => $conversation->lastMessage->subject, 'sender' => $conversation->lastMessage->sender?->name, 'priority' => $conversation->lastMessage->priority, 'sent_at' => $conversation->lastMessage->sent_at] : null, 'unread_count' => (int) ($conversation->unread_count ?? 0), 'pinned' => (bool) $participant?->pinned_at, 'muted' => (bool) $participant?->muted_until?->isFuture(), 'archived' => (bool) $participant?->archived_at, 'participants' => $detail ? $conversation->participants->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user?->name, 'email' => $p->user?->email, 'photo' => $p->user?->profile_photo_url, 'role' => $p->role, 'left_at' => $p->left_at])->values() : $others->take(4)->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user?->name, 'photo' => $p->user?->profile_photo_url])->values()];
    }
}
