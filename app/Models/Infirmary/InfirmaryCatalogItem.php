<?php

namespace App\Models\Infirmary;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfirmaryCatalogItem extends Model
{
    use HasFactory;

    public const GROUP_ATTENTION_CATEGORY = 'attention_category';

    protected $fillable = [
        'group_key',
        'code',
        'name',
        'description',
        'metadata',
        'sort_order',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function scopeGroup($query, string $groupKey)
    {
        return $query->where('group_key', $groupKey);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attentions(): HasMany
    {
        return $this->hasMany(InfirmaryAttention::class, 'attention_category', 'code');
    }

    public static function optionsForGroup(string $groupKey): array
    {
        return self::query()
            ->group($groupKey)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (self $item) => [
                'value' => $item->code,
                'label' => $item->name,
            ])
            ->all();
    }
}
