<?php

namespace App\Models\Library;

use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaObra extends Model
{
    use HasFactory;

    public const MATERIAL_TYPES = [
        'libro',
        'diccionario',
        'enciclopedia',
        'tablet',
        'notebook',
        'proyector',
        'parlante',
        'juego_educativo',
        'material_didactico',
        'kit_pedagogico',
        'audiovisual',
        'otro',
    ];

    public const STATUS_OPTIONS = [
        'disponible',
        'prestado',
        'reservado',
        'en_reparacion',
        'danado',
        'perdido',
        'dado_de_baja',
    ];

    protected $table = 'biblioteca_obras';

    protected $fillable = [
        'material_type',
        'title',
        'subtitle',
        'main_author',
        'secondary_authors',
        'publisher',
        'publication_year',
        'isbn',
        'category',
        'subcategory',
        'genre',
        'recommended_level',
        'recommended_course_section_id',
        'language',
        'page_count',
        'description',
        'keywords',
        'cover_image_url',
        'internal_code',
        'barcode',
        'physical_location',
        'shelf',
        'section',
        'general_status',
        'observations',
        'total_copies',
        'available_copies',
        'loan_count',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'secondary_authors' => 'array',
        'keywords' => 'array',
        'publication_year' => 'integer',
        'page_count' => 'integer',
        'total_copies' => 'integer',
        'available_copies' => 'integer',
        'loan_count' => 'integer',
    ];

    public function recommendedCourse(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'recommended_course_section_id');
    }

    public function ejemplares(): HasMany
    {
        return $this->hasMany(BibliotecaEjemplar::class)->orderBy('code');
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(BibliotecaPrestamo::class)->latest('borrowed_at');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(BibliotecaReserva::class)->latest('requested_at');
    }

    public function planesLectores(): HasMany
    {
        return $this->hasMany(BibliotecaPlanLector::class)->latest('start_date');
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
