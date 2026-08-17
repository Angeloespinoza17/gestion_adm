<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Protocol extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'social_work_protocols';
    protected $casts = ['active' => 'boolean', 'effective_from' => 'date:Y-m-d'];
    public function versions(): HasMany { return $this->hasMany(ProtocolVersion::class, 'protocol_id')->latest('version'); }
}
