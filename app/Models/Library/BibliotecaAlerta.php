<?php

namespace App\Models\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibliotecaAlerta extends Model
{
    use HasFactory;

    protected $table = 'biblioteca_alertas';

    protected $fillable = [
        'alert_type',
        'alert_level',
        'title',
        'message',
        'status',
        'due_at',
        'related_type',
        'related_id',
        'recipient_scope',
        'recipient_user_id',
        'metadata',
        'resolved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
