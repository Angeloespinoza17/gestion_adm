<?php

namespace App\Policies;

use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    public function view(User $user, Message $message): Response
    {
        return $this->isActiveParticipant($user, $message)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Message $message): Response
    {
        if (! $this->isActiveParticipant($user, $message)) {
            return Response::denyAsNotFound();
        }

        return $message->sender_id === $user->id
            && ! $message->requires_acknowledgement
            && $message->kind === 'chat'
            && $message->sent_at?->gte(now()->subMinutes(config('messaging.messages.edit_window_minutes')))
                ? Response::allow()
                : Response::deny();
    }

    public function delete(User $user, Message $message): Response
    {
        return $this->update($user, $message);
    }

    public function receipts(User $user, Message $message): bool
    {
        return $user->canUseMessaging()
            && ($message->sender_id === $user->id || $user->hasPermission('messaging.view_receipts'));
    }

    private function isActiveParticipant(User $user, Message $message): bool
    {
        return $user->canUseMessaging()
            && $message->conversation->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->exists();
    }
}
