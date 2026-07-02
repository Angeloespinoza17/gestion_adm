<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarInstitution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'website_url',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'institution_id');
    }
}
