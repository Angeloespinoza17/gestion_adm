<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SignatureAttempt extends LibroDigitalModel
{
    use HasPublicUlid;

    public const UPDATED_AT = null;

    protected $table = 'lcd_signature_attempts';

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(TeacherSignature::class, 'teacher_signature_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }
}
