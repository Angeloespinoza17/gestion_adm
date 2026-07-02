<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'responsible_staff_id',
        'active',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class)->withTimestamps();
    }

    public function dependencyReservations(): HasMany
    {
        return $this->hasMany(DependencyReservation::class);
    }

    public function permissionRequests(): BelongsToMany
    {
        return $this->belongsToMany(PermissionRequest::class, 'permission_request_department')->withTimestamps();
    }

    public function porterReceivedItems(): HasMany
    {
        return $this->hasMany(PorterReceivedItem::class)->orderByDesc('received_at');
    }

    public function porterGoodsMovements(): HasMany
    {
        return $this->hasMany(PorterGoodsMovement::class)->orderByDesc('moved_at');
    }

    public function relevantCalendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class)->orderBy('start_date')->orderBy('start_time');
    }
}
