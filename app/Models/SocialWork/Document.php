<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class Document extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'social_work_documents';
    protected $casts = ['tags' => 'array', 'valid_until' => 'date:Y-m-d'];
    protected $hidden = ['private_path'];
    public function documentable(): MorphTo { return $this->morphTo(); }
}
