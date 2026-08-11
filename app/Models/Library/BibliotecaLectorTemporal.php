<?php

namespace App\Models\Library;

use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaLectorTemporal extends Model
{
    public const PERSON_CATEGORIES = [
        'exalumno',
        'exfuncionario',
        'apoderado',
        'visitante',
        'practicante',
        'otro',
    ];

    protected $table = 'biblioteca_lectores_temporales';

    protected $fillable = [
        'full_name',
        'rut',
        'person_category',
        'email',
        'phone',
        'course_name',
        'notes',
        'active',
        'linked_student_profile_id',
        'linked_staff_id',
        'source_system',
        'source_id',
        'source_metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'source_metadata' => 'array',
    ];

    public function linkedStudent(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'linked_student_profile_id');
    }

    public function linkedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'linked_staff_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BibliotecaPrestamo::class, 'biblioteca_lector_temporal_id');
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
