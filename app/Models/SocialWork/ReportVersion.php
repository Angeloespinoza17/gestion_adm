<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReportVersion extends SocialWorkModel {
    protected $table = 'social_work_report_versions';
    protected $casts = ['source_sections' => 'array', 'source_snapshot' => 'array', 'excluded_sections' => 'array'];
    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
}
