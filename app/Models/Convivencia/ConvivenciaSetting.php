<?php

namespace App\Models\Convivencia;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConvivenciaSetting extends Model
{
    use HasFactory;

    protected $table = 'convivencia_settings';

    protected $fillable = [
        'key',
        'label',
        'value',
        'description',
        'active',
    ];

    protected $casts = [
        'value' => 'array',
        'active' => 'boolean',
    ];
}
