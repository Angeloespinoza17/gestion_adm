<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLifePostImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'image_path',
        'alt_text',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'url',
        'preview_url',
    ];

    protected $hidden = [
        'image_path',
    ];

    public function studentLifePost(): BelongsTo
    {
        return $this->belongsTo(StudentLifePost::class);
    }

    public function getUrlAttribute(): string
    {
        return route('public.student-life.gallery', [
            'studentLifePost' => $this->student_life_post_id,
            'studentLifePostImage' => $this->id,
        ], false);
    }

    public function getPreviewUrlAttribute(): string
    {
        return route('api.admin.student-life.gallery', [
            'studentLifePost' => $this->student_life_post_id,
            'studentLifePostImage' => $this->id,
        ], false);
    }
}
