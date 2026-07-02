<?php

namespace App\Models\ApoyoProfesional;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApoyoAdjunto extends Model
{
    use HasFactory;

    protected $table = 'apoyo_adjuntos';

    public const CATEGORY_OPTIONS = [
        ['value' => 'pdf', 'label' => 'PDF'],
        ['value' => 'imagen', 'label' => 'Imagen'],
        ['value' => 'documento_word', 'label' => 'Documento Word'],
        ['value' => 'informe', 'label' => 'Informe'],
        ['value' => 'certificado', 'label' => 'Certificado'],
        ['value' => 'pauta', 'label' => 'Pauta'],
        ['value' => 'acta', 'label' => 'Acta'],
        ['value' => 'registro_externo', 'label' => 'Registro externo'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $fillable = [
        'student_profile_id',
        'category',
        'confidentiality_level',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'notes',
        'uploaded_by',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
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
