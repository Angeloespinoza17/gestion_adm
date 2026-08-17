<?php

namespace App\Policies;

use App\Models\Messaging\MessageAttachment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessageAttachmentPolicy
{
    public function view(User $user, MessageAttachment $attachment): Response
    {
        return $attachment->message->conversation->participants()->where('user_id', $user->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
