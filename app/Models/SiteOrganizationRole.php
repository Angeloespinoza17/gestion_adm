<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteOrganizationRole extends Model
{
    public const SECTION_LEADERSHIP = 'leadership';

    public const SECTION_MEMBER = 'member';

    public const SECTION_ADVISOR = 'advisor';

    public const SECTIONS = [
        self::SECTION_LEADERSHIP,
        self::SECTION_MEMBER,
        self::SECTION_ADVISOR,
    ];

    protected $fillable = [
        'organization_type',
        'name',
        'section',
        'sort_order',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(SiteOrganizationMember::class, 'role_id');
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
