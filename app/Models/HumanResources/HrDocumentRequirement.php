<?php

namespace App\Models\HumanResources;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrDocumentRequirement extends HumanResourcesModel
{
    use SoftDeletes;

    public const VALIDITY_NONE = 'none';

    public const VALIDITY_MONTHS = 'months';

    public const VALIDITY_MANUAL = 'manual';

    protected $table = 'hr_document_requirements';

    protected $casts = [
        'requires_delivery' => 'boolean',
        'requires_signature' => 'boolean',
        'validity_months' => 'integer',
        'alert_days' => 'integer',
        'is_required' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function controls(): HasMany
    {
        return $this->hasMany(HrDocumentControl::class, 'document_requirement_id');
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
