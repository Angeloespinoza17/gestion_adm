<?php

namespace App\Models\Infirmary;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InfirmaryDocument extends Model
{
    use HasFactory;

    protected $table = 'infirmary_documents';

    protected $fillable = [
        'student_profile_id',
        'category',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
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
