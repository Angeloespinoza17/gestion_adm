<?php
namespace App\Models\SocialWork;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class JunaebDelivery extends SocialWorkModel {
    use SoftDeletes; protected $table = 'junaeb_deliveries'; protected $casts = ['received_by_school_on' => 'date:Y-m-d', 'delivered_on' => 'date:Y-m-d'];
    public function benefit(): BelongsTo { return $this->belongsTo(JunaebBenefit::class, 'student_junaeb_benefit_id'); }
    public function items(): HasMany { return $this->hasMany(JunaebDeliveryItem::class, 'delivery_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
}
