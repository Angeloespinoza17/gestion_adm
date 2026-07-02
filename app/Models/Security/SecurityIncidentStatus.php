<?php

namespace App\Models\Security;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityIncidentStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'color',
        'sort_order',
        'is_closed',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function incidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class, 'status_id');
    }

    public static function defaultCode(): string
    {
        return 'pendiente';
    }
}
