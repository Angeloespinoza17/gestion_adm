<?php

namespace App\Services\Messaging;

use App\Models\Messaging\Conversation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationService
{
    public function __construct(private AuditService $audit, private MessagingBroadcaster $broadcaster) {}

    public function direct(User $actor, User $other): Conversation
    {
        abort_if($actor->id === $other->id || ! $other->active, 422, 'Selecciona otro usuario activo.');
        $ids = [$actor->id, $other->id];
        sort($ids);
        $key = hash('sha256', implode(':', $ids));

        try {
            return DB::transaction(function () use ($actor, $other, $key) {
                $conversation = Conversation::query()->firstOrCreate(['direct_key' => $key], ['public_id' => (string) Str::ulid(), 'type' => 'direct', 'created_by' => $actor->id]);
                $conversation->participants()->updateOrCreate(['user_id' => $actor->id], ['role' => 'member', 'can_write' => true, 'joined_at' => now(), 'left_at' => null]);
                $conversation->participants()->updateOrCreate(['user_id' => $other->id], ['role' => 'member', 'can_write' => true, 'joined_at' => now(), 'left_at' => null]);
                $this->audit->record('conversation_created', $actor->id, $conversation->id);
                DB::afterCommit(fn () => $this->broadcaster->conversationChanged($conversation, 'conversation_created', ['actor_id' => $actor->id]));

                return $conversation;
            });
        } catch (QueryException) {
            return Conversation::query()->where('direct_key', $key)->firstOrFail();
        }
    }

    public function group(User $actor, array $data, string $type = 'group'): Conversation
    {
        return DB::transaction(function () use ($actor, $data, $type) {
            $conversation = Conversation::query()->create(['public_id' => (string) Str::ulid(), 'type' => $type, 'title' => trim($data['title']), 'description' => $data['description'] ?? null, 'created_by' => $actor->id, 'only_admins_can_write' => $data['only_admins_can_write'] ?? false]);
            $ids = collect($data['user_ids'] ?? [])->push($actor->id)->unique()->values();
            $users = User::query()->where('active', true)->whereIn('id', $ids)->get(['id', 'name']);
            foreach ($users as $user) {
                $conversation->participants()->create(['user_id' => $user->id, 'role' => $user->id === $actor->id ? 'owner' : 'member', 'can_write' => true, 'joined_at' => now()]);
            }
            abort_if($users->count() < 2, 422, 'La conversación requiere al menos dos participantes activos.');
            $this->audit->record('conversation_created', $actor->id, $conversation->id, null, null, ['type' => $type]);
            DB::afterCommit(fn () => $this->broadcaster->conversationChanged($conversation, 'conversation_created', ['actor_id' => $actor->id]));

            return $conversation;
        });
    }

    public function announcement(User $actor, array $data): Conversation
    {
        abort_unless($actor->isSuperAdmin() || $actor->hasPermission('messaging.send_announcement') || $actor->hasPermission('gestionar_comunicaciones_internas'), 403);
        $ids = collect($data['user_ids'] ?? []);
        if ($data['audience_all'] ?? false) {
            $ids = User::query()->where('active', true)->pluck('id');
        }
        if (! empty($data['role_ids'])) {
            $ids = $ids->merge(User::query()->where('active', true)->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $data['role_ids']))->pluck('id'));
        }
        $data['user_ids'] = $ids->push($actor->id)->unique()->values()->all();

        return $this->group($actor, $data, 'announcement');
    }
}
