<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaProtocol extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_protocols';

    public const STATUS_OPTIONS = [
        ['value' => 'activo', 'label' => 'Activo'],
        ['value' => 'borrador', 'label' => 'Borrador'],
        ['value' => 'inactivo', 'label' => 'Inactivo'],
    ];

    protected $fillable = [
        'protocol_type_item_id',
        'criticality_item_id',
        'name',
        'type_label',
        'criticality_label',
        'description',
        'required_documents',
        'safeguard_measures',
        'minimal_actions',
        'default_due_days',
        'status',
        'is_sensitive',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'default_due_days' => 'integer',
        'is_sensitive' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'protocol_type_item_id');
    }

    public function criticality(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'criticality_item_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolStep::class, 'protocol_id')->orderBy('step_order');
    }

    public function activations(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivation::class, 'protocol_id')->latest('activated_at');
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
