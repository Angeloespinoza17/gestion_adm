<?php

namespace App\Models\CentroApuntes;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroApuntesAsignatura extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        'activa',
        'inactiva',
    ];

    public const AREA_OPTIONS = [
        'Lenguaje',
        'Matemática',
        'Historia y Ciencias Sociales',
        'Ciencias Naturales',
        'Inglés',
        'Religión',
        'Artes',
        'Música',
        'Educación Física',
        'Tecnología',
        'Orientación',
        'Filosofía',
        'Biología',
        'Física',
        'Química',
        'Convivencia Escolar',
        'PIE',
    ];

    public const EDUCATION_LEVEL_OPTIONS = [
        'Educación Parvularia',
        '1° a 4° Básico',
        '5° a 8° Básico',
        '1° a 4° Medio',
        'Transversal',
    ];

    protected $table = 'centro_apuntes_asignaturas';

    protected $fillable = [
        'name',
        'code',
        'area',
        'education_level',
        'status',
        'observations',
        'created_by',
        'updated_by',
    ];

    public function solicitudes(): HasMany
    {
        return $this->hasMany(CentroApuntesSolicitud::class, 'subject_id')->latest('requested_at');
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
