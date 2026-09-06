<?php

namespace App\Models\Operational;

use App\Models\Staff;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalStaffLogEntry extends Model
{
    use HasFactory;

    public const CATEGORY_OPTIONS = [
        ['value' => 'general', 'label' => 'Observación general', 'icon' => 'bx-note'],
        ['value' => 'management', 'label' => 'Gestión o tarea', 'icon' => 'bx-check-square'],
        ['value' => 'meeting', 'label' => 'Reunión o acuerdo', 'icon' => 'bx-group'],
        ['value' => 'follow_up', 'label' => 'Seguimiento', 'icon' => 'bx-trending-up'],
        ['value' => 'incident', 'label' => 'Incidente', 'icon' => 'bx-error-circle'],
        ['value' => 'improvement', 'label' => 'Idea o mejora', 'icon' => 'bx-bulb'],
        ['value' => 'other', 'label' => 'Otra categoría', 'icon' => 'bx-dots-horizontal-rounded'],
    ];

    protected $fillable = [
        'owner_user_id',
        'staff_id',
        'owner_name_snapshot',
        'occurred_at',
        'category',
        'custom_category',
        'title',
        'details',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('owner_user_id', $user->id);
    }

    public function categoryLabel(): string
    {
        if ($this->category === 'other' && filled($this->custom_category)) {
            return $this->custom_category;
        }

        return collect(self::CATEGORY_OPTIONS)->firstWhere('value', $this->category)['label'] ?? $this->category;
    }
}
