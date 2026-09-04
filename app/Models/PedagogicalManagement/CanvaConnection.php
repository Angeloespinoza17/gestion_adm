<?php

namespace App\Models\PedagogicalManagement;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanvaConnection extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['access_token_encrypted', 'refresh_token_encrypted'];

    protected function casts(): array
    {
        return [
            'access_token_encrypted' => 'encrypted',
            'refresh_token_encrypted' => 'encrypted',
            'scopes' => 'array',
            'capabilities' => 'array',
            'status' => CanvaConnectionStatus::class,
            'access_token_expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(ClassPresentation::class);
    }
}
