<?php

namespace App\Models\Convivencia;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvivenciaSociogramQuestion extends Model
{
    use HasFactory;

    protected $table = 'convivencia_sociogram_questions';

    public const SELECTION_OPTIONS = [
        ['value' => 'positiva', 'label' => 'Positiva'],
        ['value' => 'negativa', 'label' => 'Negativa'],
        ['value' => 'neutra', 'label' => 'Neutra'],
    ];

    protected $fillable = [
        'sociogram_id',
        'prompt',
        'selection_type',
        'max_choices',
        'active',
    ];

    protected $casts = [
        'max_choices' => 'integer',
        'active' => 'boolean',
    ];

    public function sociogram(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaSociogram::class, 'sociogram_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ConvivenciaSociogramAnswer::class, 'question_id')->latest('id');
    }
}
