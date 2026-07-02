<?php

namespace App\Models\Convivencia;

use App\Models\Department;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvivenciaPlanAction extends Model
{
    use HasFactory;

    protected $table = 'convivencia_plan_actions';

    public const TYPE_OPTIONS = [
        ['value' => 'preventiva', 'label' => 'Preventiva'],
        ['value' => 'promocional', 'label' => 'Promocional'],
        ['value' => 'formativa', 'label' => 'Formativa'],
        ['value' => 'reactiva', 'label' => 'Reactiva'],
    ];

    protected $fillable = [
        'plan_id',
        'dimension_item_id',
        'responsible_user_id',
        'responsible_staff_id',
        'responsible_department_id',
        'action_type',
        'title',
        'description',
        'dimension_label',
        'responsible_label',
        'starts_on',
        'ends_on',
        'required_resources',
        'indicator_summary',
        'verification_means',
        'status',
        'advance_percentage',
        'observations',
        'evidence_summary',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'advance_percentage' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaPlan::class, 'plan_id');
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'dimension_item_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function responsibleDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'responsible_department_id');
    }
}
