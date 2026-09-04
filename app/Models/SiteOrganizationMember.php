<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteOrganizationMember extends Model
{
    public const KIND_EXTERNAL = 'external';

    public const KIND_STUDENT = 'student';

    public const KIND_STAFF = 'staff';

    public const KINDS = [self::KIND_EXTERNAL, self::KIND_STUDENT, self::KIND_STAFF];

    protected $fillable = [
        'site_organization_id',
        'role_id',
        'member_kind',
        'student_profile_id',
        'staff_id',
        'display_name_snapshot',
        'detail_snapshot',
        'section',
        'sort_order',
        'public_name_authorized',
        'public_name_authorized_at',
        'public_name_authorized_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'public_name_authorized' => 'boolean',
        'public_name_authorized_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(SiteOrganization::class, 'site_organization_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(SiteOrganizationRole::class, 'role_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function publicNameAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'public_name_authorized_by');
    }
}
