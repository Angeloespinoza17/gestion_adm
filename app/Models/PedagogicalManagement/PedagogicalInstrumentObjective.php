<?php

namespace App\Models\PedagogicalManagement;

use App\Models\LibroDigital\LearningObjective;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedagogicalInstrumentObjective extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'page_number' => 'integer'];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrument::class, 'instrument_id');
    }

    public function learningObjective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
