<?php

namespace App\Policies;

use App\Models\Messaging\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): Response
    {
        return $user->active && $conversation->participants()->where('user_id', $user->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function send(User $user, Conversation $conversation): bool
    {
        $participant = $conversation->participants()->where('user_id', $user->id)->whereNull('left_at')->first();
        if (! $participant || ! $participant->can_write || $conversation->is_locked) {
            return false;
        }

        return ! $conversation->only_admins_can_write || in_array($participant->role, ['owner', 'admin'], true);
    }

    public function manage(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->where('user_id', $user->id)->whereNull('left_at')->whereIn('role', ['owner', 'admin'])->exists();
    }
}
