<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'requires_attachment',
        'allows_with_pay',
        'allows_without_pay',
        'allows_hourly',
        'allows_half_day',
        'requires_manager_approval',
        'requires_direction_approval',
        'requires_hr_approval',
        'max_days',
        'minimum_notice_days',
        'allows_retroactive',
        'affects_salary',
        'affects_attendance',
        'requires_replacement',
        'active',
    ];

    protected $casts = [
        'requires_attachment' => 'boolean',
        'allows_with_pay' => 'boolean',
        'allows_without_pay' => 'boolean',
        'allows_hourly' => 'boolean',
        'allows_half_day' => 'boolean',
        'requires_manager_approval' => 'boolean',
        'requires_direction_approval' => 'boolean',
        'requires_hr_approval' => 'boolean',
        'max_days' => 'decimal:2',
        'minimum_notice_days' => 'integer',
        'allows_retroactive' => 'boolean',
        'affects_salary' => 'boolean',
        'affects_attendance' => 'boolean',
        'requires_replacement' => 'boolean',
        'active' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class);
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(PermissionTypeWatcher::class)->orderBy('id');
    }
}
