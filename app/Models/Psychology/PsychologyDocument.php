<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsychologyDocument extends Model
{
    use SoftDeletes;

    protected $table = 'psychology_documents';

    protected $fillable = ['case_id', 'referral_id', 'activity_id', 'category', 'description', 'private_path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'visibility', 'status', 'uploaded_by', 'deleted_by'];

    protected $hidden = ['private_path'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(PsychologyCase::class, 'case_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(PsychologyReferral::class, 'referral_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
