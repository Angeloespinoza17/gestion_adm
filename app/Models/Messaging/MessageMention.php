<?php

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Model;

class MessageMention extends Model
{
    public $timestamps = false;

    protected $fillable = ['message_id', 'user_id', 'created_at'];
}
