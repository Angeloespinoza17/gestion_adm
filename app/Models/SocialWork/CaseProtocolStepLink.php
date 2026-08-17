<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
class CaseProtocolStepLink extends SocialWorkModel { protected $table='social_work_case_protocol_step_links'; public function activation(): BelongsTo{return $this->belongsTo(CaseProtocol::class,'case_protocol_id');} public function linkable(): MorphTo{return $this->morphTo();} }
