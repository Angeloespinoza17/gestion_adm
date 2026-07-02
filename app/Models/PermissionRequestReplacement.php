<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionRequestReplacement extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'coordinado', 'label' => 'Coordinado'],
        ['value' => 'confirmado', 'label' => 'Confirmado'],
        ['value' => 'descartado', 'label' => 'Descartado'],
    ];

    protected $fillable = [
        'permission_request_id',
        'replaced_staff_id',
        'replacement_staff_id',
        'course_id',
        'course_name',
        'subject_id',
        'subject_name',
        'dependency_name',
        'schedule_detail',
        'start_datetime',
        'end_datetime',
        'status',
        'observations',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'subject_id' => 'integer',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function permissionRequest(): BelongsTo
    {
        return $this->belongsTo(PermissionRequest::class);
    }

    public function replacedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'replaced_staff_id');
    }

    public function replacementStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'replacement_staff_id');
    }
}
