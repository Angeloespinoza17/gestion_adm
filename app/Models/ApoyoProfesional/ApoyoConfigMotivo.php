<?php

namespace App\Models\ApoyoProfesional;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApoyoConfigMotivo extends Model
{
    use HasFactory;

    protected $table = 'apoyo_config_motivos';

    protected $fillable = [
        'slug',
        'name',
        'area_slug',
        'description',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
