<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cargo_id',
        'user_type',
        'active',
        'student_id',
        'guardian_id',
        'staff_id',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('slug', 'super_admin')->exists();
    }

    /**
     * @return array<int, string>
     */
    public function permissionSlugs(): array
    {
        $roles = $this->relationLoaded('roles')
            ? $this->roles
            : $this->roles()->with('permissions')->get();

        return $roles
            ->pluck('permissions')
            ->flatten()
            ->filter(fn ($permission) => $permission && $permission->active)
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permissionSlug, $this->permissionSlugs(), true);
    }
}
