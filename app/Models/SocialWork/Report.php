<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Report extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'social_work_reports';
    protected $casts = ['approved_at' => 'datetime', 'issued_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SocialCase::class, 'case_id'); }
    public function versions(): HasMany { return $this->hasMany(ReportVersion::class, 'report_id')->latest('version'); }
}
