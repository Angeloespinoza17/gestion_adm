<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\SignatureStatus;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TeacherSignature extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_teacher_signatures';

    protected function casts(): array
    {
        return ['status' => SignatureStatus::class, 'verified_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function regulatoryProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class);
    }

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherAssignment::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }
}
