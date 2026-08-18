<?php

namespace App\Policies;

use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    public function view(User $user, Message $message): Response
    {
        return $user->active && $message->conversation->participants()->where('user_id', $user->id)->whereNull('left_at')->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id && ! $message->requires_acknowledgement && $message->kind === 'chat' && $message->sent_at?->gte(now()->subMinutes(config('messaging.messages.edit_window_minutes')));
    }

    public function delete(User $user, Message $message): bool
    {
        return $this->update($user, $message);
    }

    public function receipts(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id || $user->hasPermission('messaging.view_receipts');
    }
}
