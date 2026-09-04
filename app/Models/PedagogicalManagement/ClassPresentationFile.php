<?php

namespace App\Models\PedagogicalManagement;

use App\Enums\PedagogicalManagement\ClassPresentationFileType;
use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassPresentationFile extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'type' => ClassPresentationFileType::class,
            'size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ClassPresentation::class, 'class_presentation_id');
    }
}
