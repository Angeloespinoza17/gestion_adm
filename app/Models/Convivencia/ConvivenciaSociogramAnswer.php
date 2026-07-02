<?php

namespace App\Models\Convivencia;

use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvivenciaSociogramAnswer extends Model
{
    use HasFactory;

    protected $table = 'convivencia_sociogram_answers';

    protected $fillable = [
        'sociogram_id',
        'question_id',
        'respondent_student_id',
        'selected_student_id',
        'selection_type',
        'notes',
    ];

    public function sociogram(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaSociogram::class, 'sociogram_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaSociogramQuestion::class, 'question_id');
    }

    public function respondentStudent(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'respondent_student_id');
    }

    public function selectedStudent(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'selected_student_id');
    }
}
