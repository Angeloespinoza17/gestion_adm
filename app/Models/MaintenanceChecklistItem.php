<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'system',
        'subdimension',
        'review',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
