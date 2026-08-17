<?php

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Model;

class MessageReaction extends Model
{
    protected $fillable = ['message_id', 'user_id', 'reaction'];
}
