<?php

namespace App\Models\PedagogicalManagement;

use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassPresentationReferenceFile extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ClassPresentation::class, 'class_presentation_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
