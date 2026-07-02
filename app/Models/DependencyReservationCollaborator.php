<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DependencyReservationCollaborator extends Model
{
    use HasFactory;

    protected $fillable = [
        'dependency_reservation_id',
        'staff_id',
        'external_email',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(DependencyReservation::class, 'dependency_reservation_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
