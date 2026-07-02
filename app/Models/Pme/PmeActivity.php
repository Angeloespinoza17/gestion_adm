<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeActivity extends Model
{
    use SoftDeletes;

    protected $table = 'pme_actividades';

    protected $fillable = [
        'pme_action_id',
        'name',
        'description',
        'responsible_user_id',
        'scheduled_date',
        'completed_date',
        'state',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date:Y-m-d',
        'completed_date' => 'date:Y-m-d',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(PmeAction::class, 'pme_action_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PmeEvidence::class, 'pme_activity_id')->latest('uploaded_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
