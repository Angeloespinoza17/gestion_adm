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
        abort_unless($actor->canUseMessaging(), 403, 'La mensajería institucional está disponible exclusivamente para funcionarios.');
        abort_if($actor->id === $other->id || ! $other->canUseMessaging(), 422, 'Selecciona otro funcionario habilitado.');
        $ids = [$actor->id, $other->id];
        sort($ids);
        $key = hash('sha256', implode(':', $ids));

        try {
            return DB::transaction(function () use ($actor, $other, $key) {
                abort_unless(
                    User::query()->messagingStaff()->whereKey([$actor->id, $other->id])->lockForUpdate()->pluck('id')->count() === 2,
                    422,
                    'Selecciona otro funcionario habilitado.'
                );
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
        abort_unless($actor->canUseMessaging(), 403, 'La mensajería institucional está disponible exclusivamente para funcionarios.');

        return DB::transaction(function () use ($actor, $data, $type) {
            $chunkSize = max(1, (int) config('messaging.announcements.chunk_size', 500));
            $requestedIds = collect($data['user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->push((int) $actor->id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();
            $activeUserIds = [];

            foreach ($requestedIds->chunk($chunkSize) as $idChunk) {
                $users = User::query()
                    ->whereIn('id', $idChunk->all())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id', 'active', 'user_type', 'staff_id', 'student_id', 'guardian_id']);

                abort_if(
                    $users->count() !== $idChunk->count()
                        || $users->contains(fn (User $user) => ! $user->canUseMessaging()),
                    422,
                    'Las conversaciones sólo pueden incluir cuentas de funcionarios habilitados.'
                );

                foreach ($users as $user) {
                    $activeUserIds[] = (int) $user->id;
                }
            }

            abort_if(
                count($activeUserIds) < 2 || ! in_array((int) $actor->id, $activeUserIds, true),
                422,
                'La conversación requiere al menos dos participantes activos.'
            );

            $conversation = Conversation::query()->create(['public_id' => (string) Str::ulid(), 'type' => $type, 'title' => trim($data['title']), 'description' => $data['description'] ?? null, 'created_by' => $actor->id, 'only_admins_can_write' => $data['only_admins_can_write'] ?? false]);
            $timestamp = now();

            foreach (array_chunk($activeUserIds, $chunkSize) as $userIdChunk) {
                DB::table('conversation_participants')->insert(array_map(
                    fn (int $userId) => [
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'role' => $userId === (int) $actor->id ? 'owner' : 'member',
                        'can_write' => true,
                        'joined_at' => $timestamp,
                        'left_at' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                    $userIdChunk
                ));
            }

            $this->audit->record('conversation_created', $actor->id, $conversation->id, null, null, ['type' => $type]);
            DB::afterCommit(fn () => $this->broadcaster->conversationChanged($conversation, 'conversation_created', ['actor_id' => $actor->id]));

            return $conversation;
        });
    }

    public function announcement(User $actor, array $data): Conversation
    {
        abort_unless($actor->canUseMessaging(), 403, 'La mensajería institucional está disponible exclusivamente para funcionarios.');
        abort_unless($actor->isSuperAdmin() || $actor->hasPermission('messaging.send_announcement') || $actor->hasPermission('gestionar_comunicaciones_internas'), 403);
        $ids = collect($data['user_ids'] ?? []);
        if ($data['audience_all'] ?? false) {
            $ids = $ids->merge(User::query()->messagingStaff()->pluck('id'));
        }
        if (! empty($data['role_ids'])) {
            $ids = $ids->merge(User::query()->messagingStaff()->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $data['role_ids']))->pluck('id'));
        }
        $data['user_ids'] = $ids->push($actor->id)->unique()->values()->all();

        return $this->group($actor, $data, 'announcement');
    }
}
