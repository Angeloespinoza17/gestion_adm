<?php

namespace App\Models\Convivencia;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConvivenciaAttachment extends Model
{
    use HasFactory;

    protected $table = 'convivencia_attachments';

    public const CATEGORY_OPTIONS = [
        ['value' => 'pdf', 'label' => 'PDF'],
        ['value' => 'imagen', 'label' => 'Imagen'],
        ['value' => 'acta', 'label' => 'Acta'],
        ['value' => 'informe', 'label' => 'Informe'],
        ['value' => 'evidencia', 'label' => 'Evidencia'],
        ['value' => 'captura', 'label' => 'Captura'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $fillable = [
        'case_id',
        'student_profile_id',
        'category',
        'confidentiality_level',
        'is_sensitive',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_sensitive' => 'boolean',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class, 'case_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
