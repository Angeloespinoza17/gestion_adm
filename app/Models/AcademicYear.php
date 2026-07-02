<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'year',
        'starts_at',
        'ends_at',
        'is_active',
        'is_closed',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'is_closed' => 'boolean',
    ];

    public function courseSections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('display_name');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class)->orderBy('snapshot_course_display_name');
    }

    public function enrollmentMovements(): HasMany
    {
        return $this->hasMany(StudentEnrollmentMovement::class)->orderByDesc('effective_date')->orderByDesc('id');
    }

    public function promotionsFrom(): HasMany
    {
        return $this->hasMany(StudentPromotion::class, 'from_academic_year_id')->orderByDesc('id');
    }

    public function promotionsTo(): HasMany
    {
        return $this->hasMany(StudentPromotion::class, 'to_academic_year_id')->orderByDesc('id');
    }

    public function porterWithdrawals(): HasMany
    {
        return $this->hasMany(PorterStudentWithdrawal::class)->orderByDesc('withdrawn_at')->orderByDesc('id');
    }

    public function porterReceivedItems(): HasMany
    {
        return $this->hasMany(PorterReceivedItem::class)->orderByDesc('received_at')->orderByDesc('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('year')->orderByDesc('id');
    }
}
