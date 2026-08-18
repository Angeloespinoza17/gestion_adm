<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyActivityAddendum extends Model
{
    protected $table = 'psychology_activity_addenda';

    protected $fillable = ['activity_id', 'content', 'visibility', 'reason', 'created_by'];

    protected $casts = ['content' => 'encrypted'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
