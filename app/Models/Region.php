<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'abbreviation',
        'iso_code',
        'sort_order',
    ];

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
