<?php

namespace App\Models\ApoyoProfesional;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApoyoConfigAttentionType extends Model
{
    use HasFactory;

    protected $table = 'apoyo_config_tipos_atencion';

    protected $fillable = [
        'slug',
        'name',
        'requires_other_description',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'requires_other_description' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
