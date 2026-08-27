<?php

namespace App\Models\PedagogicalManagement;

use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedagogicalGuidanceDocument extends Model
{
    use HasPublicUuid, SoftDeletes;

    public const TYPES = ['evaluation_regulation', 'rubric', 'guideline', 'other'];

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(PedagogicalInstrumentReview::class, 'pedagogical_review_guidance_document', 'guidance_document_id', 'review_id')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
