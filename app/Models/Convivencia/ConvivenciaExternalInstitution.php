<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaExternalInstitution extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_external_institutions';

    protected $fillable = [
        'category',
        'name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'notes',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function derivations(): HasMany
    {
        return $this->hasMany(ConvivenciaDerivation::class, 'external_institution_id')->latest('derived_at');
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
