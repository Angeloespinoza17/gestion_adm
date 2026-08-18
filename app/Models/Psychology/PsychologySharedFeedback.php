<?php
namespace App\Models\Psychology;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PsychologySharedFeedback extends Model
{
    protected $table = 'psychology_shared_feedback';
    protected $guarded = ['id'];
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
