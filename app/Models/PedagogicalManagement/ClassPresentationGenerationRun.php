<?php

namespace App\Models\PedagogicalManagement;

use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassPresentationGenerationRun extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => ClassPresentationStatus::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ClassPresentation::class, 'class_presentation_id');
    }
}
