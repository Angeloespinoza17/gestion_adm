<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CaseProtocol extends SocialWorkModel {
    protected $table = 'social_work_case_protocols';
    protected $casts = ['activated_at' => 'datetime', 'due_at' => 'datetime', 'version_snapshot' => 'array'];
    public function case(): BelongsTo { return $this->belongsTo(SocialCase::class, 'case_id'); }
    public function protocol(): BelongsTo { return $this->belongsTo(Protocol::class); }
    public function version(): BelongsTo { return $this->belongsTo(ProtocolVersion::class, 'protocol_version_id'); }
    public function stepLinks(): HasMany { return $this->hasMany(CaseProtocolStepLink::class, 'case_protocol_id'); }
}
