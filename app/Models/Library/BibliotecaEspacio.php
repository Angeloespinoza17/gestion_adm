<?php

namespace App\Models\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaEspacio extends Model
{
    use HasFactory;

    protected $table = 'biblioteca_espacios';

    protected $fillable = [
        'name',
        'location',
        'capacity',
        'resources',
        'active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'resources' => 'array',
        'active' => 'boolean',
    ];

    public function usos(): HasMany
    {
        return $this->hasMany(BibliotecaUsoEspacio::class, 'biblioteca_espacio_id')->latest('start_at');
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
