<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProtocolVersion extends SocialWorkModel {
    protected $table = 'social_work_protocol_versions';
    protected $casts = ['steps' => 'array', 'required_documents' => 'array', 'safeguards' => 'array', 'fields' => 'array', 'effective_from' => 'date:Y-m-d'];
    public function protocol(): BelongsTo { return $this->belongsTo(Protocol::class); }
}
