<?php

namespace App\Models\Pme;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmePlan extends Model
{
    use SoftDeletes;

    public const STATE_OPTIONS = [
        'borrador',
        'en_planificacion',
        'en_ejecucion',
        'en_monitoreo',
        'cerrado',
        'archivado',
    ];

    public const CYCLE_OPTIONS = [
        'diagnostico',
        'planificacion',
        'implementacion',
        'monitoreo',
        'evaluacion',
        'cierre',
    ];

    protected $table = 'pme_planes';

    protected $fillable = [
        'academic_year_id',
        'school_year',
        'name',
        'period_label',
        'cycle_name',
        'start_date',
        'end_date',
        'responsible_user_id',
        'state',
        'is_active',
        'general_description',
        'observations',
        'cloned_from_plan_id',
        'closed_at',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'school_year' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'closed_at' => 'datetime',
        'archived_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_plan_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(PmeCycle::class, 'pme_plan_id')->orderBy('sort_order');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(PmeObjective::class, 'pme_plan_id')->latest('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(PmeAction::class, 'pme_plan_id')->latest('id');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(PmeSepIncome::class, 'pme_plan_id')->orderBy('school_year')->orderBy('month');
    }

    public function monitorings(): HasMany
    {
        return $this->hasMany(PmeReflectiveMonitoring::class, 'pme_plan_id')->latest('monitored_at');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PmeGeneratedReport::class, 'pme_plan_id')->latest('generated_at');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(PmeAlert::class, 'pme_plan_id')->latest('id');
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
