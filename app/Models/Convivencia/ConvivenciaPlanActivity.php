<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaPlanActivity extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_GROUP = 'plan_activity_type';

    protected $table = 'convivencia_plan_activities';

    public const STATUS_OPTIONS = [
        ['value' => 'programada', 'label' => 'Programada'],
        ['value' => 'en_ejecucion', 'label' => 'En ejecución'],
        ['value' => 'realizada', 'label' => 'Realizada'],
        ['value' => 'cancelada', 'label' => 'Cancelada'],
    ];

    protected $fillable = [
        'plan_action_id',
        'activity_type_item_id',
        'activity_type_label',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'status',
        'contribution_percent',
        'completion_percent',
        'revision',
        'location',
        'target_audience',
        'attendee_count',
        'results',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'contribution_percent' => 'integer',
        'completion_percent' => 'integer',
        'revision' => 'integer',
        'attendee_count' => 'integer',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaPlanAction::class, 'plan_action_id')->withTrashed();
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'activity_type_item_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(ConvivenciaAttachment::class, 'attachable')->latest('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
