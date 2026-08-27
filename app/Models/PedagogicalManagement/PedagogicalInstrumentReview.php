<?php

namespace App\Models\PedagogicalManagement;

use App\Enums\PedagogicalManagement\ReviewDecision;
use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PedagogicalInstrumentReview extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'share_ai_report' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrument::class, 'instrument_id');
    }

    public function instrumentFile(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrumentFile::class, 'instrument_file_id');
    }

    public function aiReport(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrumentAiReport::class, 'ai_report_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function guidanceDocuments(): BelongsToMany
    {
        return $this->belongsToMany(PedagogicalGuidanceDocument::class, 'pedagogical_review_guidance_document', 'review_id', 'guidance_document_id')->withTimestamps();
    }

    public function printRequest(): HasOne
    {
        return $this->hasOne(PedagogicalInstrumentPrintRequest::class, 'review_id');
    }
}
