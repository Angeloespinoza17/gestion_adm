<?php

namespace App\Events\Messaging;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;

trait BroadcastsToMessagingUsers
{
    public function broadcastOn(): array
    {
        if (! config('messaging.enabled') || ! config('messaging.realtime.enabled')) {
            return [];
        }

        // Eventos serializados antes de incorporar canales por usuario no
        // contienen esta propiedad. Una cola antigua debe descartarse de
        // forma segura, nunca volver al canal amplio legado.
        $serializedRecipientIds = isset($this->recipientIds) && is_array($this->recipientIds)
            ? $this->recipientIds
            : [];
        $recipientIds = collect($serializedRecipientIds)
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return [];
        }

        $conversationPublicId = $this->change['conversation_id'] ?? $this->conversationId ?? null;
        if (! $conversationPublicId) {
            return [];
        }

        $action = $this->change['action'] ?? null;
        $removedUserId = $action === 'participant_removed'
            ? (int) ($this->change['removed_user_id'] ?? 0)
            : 0;

        $activeMemberIds = DB::table('conversation_participants as cp')
            ->join('conversations as c', 'c.id', '=', 'cp.conversation_id')
            ->where('c.public_id', $conversationPublicId)
            ->whereNull('c.deleted_at')
            ->whereIn('cp.user_id', $recipientIds->all())
            ->whereIn('cp.user_id', User::query()->messagingStaff()->select('users.id'))
            ->when(
                $removedUserId > 0,
                fn ($query) => $query->where(function ($membership) use ($removedUserId) {
                    $membership
                        ->where(function ($active) use ($removedUserId) {
                            $active->where('cp.user_id', '!=', $removedUserId)
                                ->whereNull('cp.left_at');
                        })
                        ->orWhere(function ($removed) use ($removedUserId) {
                            $removed->where('cp.user_id', $removedUserId)
                                ->whereNotNull('cp.left_at');
                        });
                }),
                fn ($query) => $query->whereNull('cp.left_at')
            )
            ->orderBy('cp.user_id')
            ->pluck('cp.user_id')
            ->map(fn ($userId) => (int) $userId);

        return $activeMemberIds
            ->unique()
            ->sort()
            ->values()
            ->map(fn (int $userId) => new PrivateChannel('messaging.user.'.$userId))
            ->all();
    }
}
