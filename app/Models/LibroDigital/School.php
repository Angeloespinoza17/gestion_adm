<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_schools';

    protected function casts(): array
    {
        return ['modalities' => 'array', 'active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lcd_school_users')
            ->withPivot(['role_snapshot', 'permission_scope', 'valid_from', 'valid_to', 'active'])
            ->withTimestamps();
    }

    public function academicYears(): BelongsToMany
    {
        return $this->belongsToMany(AcademicYear::class, 'lcd_school_academic_years')
            ->withPivot(['regulatory_profile_id', 'rbd_snapshot', 'year_snapshot', 'timezone_snapshot', 'opened_on', 'closed_on', 'active'])
            ->withTimestamps();
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
