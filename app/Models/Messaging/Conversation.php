<?php

namespace App\Models\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    protected $fillable = ['public_id', 'type', 'direct_key', 'title', 'description', 'avatar_path', 'created_by', 'last_message_id', 'last_message_at', 'is_locked', 'only_admins_can_write', 'context_type', 'context_id', 'metadata'];

    protected $casts = ['last_message_at' => 'datetime', 'is_locked' => 'boolean', 'only_admins_can_write' => 'boolean', 'metadata' => 'array'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()
            ->whereNull('left_at')
            ->whereHas('user', fn (Builder $query) => $query->messagingStaff());
    }

    public function participantPreviews(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->canUseMessaging()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('participants', fn (Builder $q) => $q->where('user_id', $user->id)->whereNull('left_at'));
    }

    public function participantFor(User $user): ?ConversationParticipant
    {
        return $this->participants()->where('user_id', $user->id)->whereNull('left_at')->first();
    }
}
