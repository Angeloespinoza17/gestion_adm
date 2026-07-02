<?php

namespace App\Models\ApoyoProfesional;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApoyoProfesionalProfile extends Model
{
    use HasFactory;

    protected $table = 'apoyo_profesionales';

    public const AREA_OPTIONS = [
        ['value' => 'psicologia', 'label' => 'Psicología'],
        ['value' => 'trabajo_social', 'label' => 'Trabajo social'],
        ['value' => 'terapia_ocupacional', 'label' => 'Terapia ocupacional'],
        ['value' => 'pie', 'label' => 'PIE'],
        ['value' => 'psicopedagogia', 'label' => 'Psicopedagogía'],
        ['value' => 'fonoaudiologia', 'label' => 'Fonoaudiología'],
        ['value' => 'orientacion', 'label' => 'Orientación'],
        ['value' => 'convivencia_escolar', 'label' => 'Convivencia Escolar'],
        ['value' => 'utp', 'label' => 'UTP'],
        ['value' => 'direccion', 'label' => 'Dirección'],
        ['value' => 'inspectoria', 'label' => 'Inspectoría'],
        ['value' => 'profesor_jefe', 'label' => 'Profesor jefe'],
        ['value' => 'pastoral', 'label' => 'Pastoral'],
        ['value' => 'otra', 'label' => 'Otra'],
    ];

    protected $fillable = [
        'user_id',
        'staff_id',
        'area_slug',
        'area_name',
        'professional_role_slug',
        'professional_role_name',
        'can_receive_derivations',
        'can_manage_confidential_cases',
        'active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'can_receive_derivations' => 'boolean',
        'can_manage_confidential_cases' => 'boolean',
        'active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function attentions(): HasMany
    {
        return $this->hasMany(ApoyoAtencion::class, 'apoyo_profesional_id')->latest('attended_at');
    }

    public function derivations(): HasMany
    {
        return $this->hasMany(ApoyoDerivacion::class, 'destination_professional_id')->latest('derived_at');
    }
}
