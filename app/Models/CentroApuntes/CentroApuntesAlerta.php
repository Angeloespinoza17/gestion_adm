<?php

namespace App\Models\CentroApuntes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentroApuntesAlerta extends Model
{
    use HasFactory;

    protected $table = 'centro_apuntes_alertas';

    protected $fillable = [
        'alert_type',
        'alert_level',
        'title',
        'message',
        'status',
        'related_type',
        'related_id',
        'metadata',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'detected_at' => 'datetime:Y-m-d H:i',
        'resolved_at' => 'datetime:Y-m-d H:i',
    ];
}
