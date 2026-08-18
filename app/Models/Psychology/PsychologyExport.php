<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyExport extends Model
{
    protected $table = 'psychology_exports';

    protected $fillable = ['format', 'status', 'filters', 'private_path', 'error_message', 'created_by', 'completed_at'];

    protected $hidden = ['private_path'];

    protected $casts = ['filters' => 'array', 'completed_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
