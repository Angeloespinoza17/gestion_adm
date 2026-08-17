<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RecordRevision extends LibroDigitalModel
{
    use HasPublicUlid;

    public const UPDATED_AT = null;

    protected $table = 'lcd_record_revisions';

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function amendmentRequest(): BelongsTo
    {
        return $this->belongsTo(AmendmentRequest::class);
    }

    public function previousRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_revision_id');
    }

    public function revisable(): MorphTo
    {
        return $this->morphTo();
    }
}
