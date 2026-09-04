<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManagedDocument extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CATEGORY_EDUCATIONAL_PROJECT = 'proyecto-educativo';

    public const CATEGORY_REGULATION = 'reglamento';

    public const CATEGORY_PROTOCOL = 'protocolo';

    public const CATEGORY_INSTITUTIONAL_PLAN = 'plan-institucional';

    public const CATEGORY_CIRCULAR = 'circular';

    public const CATEGORY_OTHER = 'otro';

    public const CATEGORIES = [
        self::CATEGORY_EDUCATIONAL_PROJECT,
        self::CATEGORY_REGULATION,
        self::CATEGORY_PROTOCOL,
        self::CATEGORY_INSTITUTIONAL_PLAN,
        self::CATEGORY_CIRCULAR,
        self::CATEGORY_OTHER,
    ];

    public const CATEGORY_LABELS = [
        self::CATEGORY_EDUCATIONAL_PROJECT => 'Proyecto educativo',
        self::CATEGORY_REGULATION => 'Reglamento',
        self::CATEGORY_PROTOCOL => 'Protocolo',
        self::CATEGORY_INSTITUTIONAL_PLAN => 'Plan institucional',
        self::CATEGORY_CIRCULAR => 'Circular',
        self::CATEGORY_OTHER => 'Otro',
    ];

    protected $fillable = [
        'title',
        'category',
        'year',
        'version',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'is_public',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'file_size' => 'integer',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('is_active', true);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }
}
