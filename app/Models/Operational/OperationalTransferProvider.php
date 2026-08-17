<?php

namespace App\Models\Operational;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalTransferProvider extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'rut', 'contact_name', 'email', 'phone', 'notes', 'active', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];

    public function quotes(): HasMany
    {
        return $this->hasMany(OperationalTransferQuote::class, 'provider_id');
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
