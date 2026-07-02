<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeMilestone extends Model
{
    use SoftDeletes;

    protected $table = 'pme_hitos';

    protected $fillable = [
        'pme_action_id',
        'name',
        'description',
        'planned_date',
        'actual_completion_date',
        'responsible_user_id',
        'progress_percentage',
        'state',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'planned_date' => 'date:Y-m-d',
        'actual_completion_date' => 'date:Y-m-d',
        'progress_percentage' => 'decimal:2',
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
        return $this->hasMany(PmeEvidence::class, 'pme_milestone_id')->latest('uploaded_at');
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
