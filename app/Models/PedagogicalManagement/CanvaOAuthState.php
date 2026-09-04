<?php

namespace App\Models\PedagogicalManagement;

use App\Models\LibroDigital\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanvaOAuthState extends Model
{
    protected $table = 'canva_oauth_states';

    protected $guarded = ['id'];

    protected $hidden = ['state_hash', 'code_verifier_encrypted'];

    protected function casts(): array
    {
        return [
            'code_verifier_encrypted' => 'encrypted',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
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
}
