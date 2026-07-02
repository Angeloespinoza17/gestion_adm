<?php

namespace App\Models;

use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityNotification;
use App\Models\Security\SecurityShift;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function createdReservations(): HasMany
    {
        return $this->hasMany(DependencyReservation::class, 'created_by');
    }

    public function approvedReservations(): HasMany
    {
        return $this->hasMany(DependencyReservation::class, 'approved_by');
    }

    public function requestedPermissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'requested_by_user_id');
    }

    public function managedPermissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'direct_manager_user_id');
    }

    public function securityShiftStarts(): HasMany
    {
        return $this->hasMany(SecurityShift::class, 'started_by_user_id');
    }

    public function securityShiftClosures(): HasMany
    {
        return $this->hasMany(SecurityShift::class, 'closed_by_user_id');
    }

    public function reportedSecurityIncidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class, 'reported_by_user_id');
    }

    public function assignedSecurityIncidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class, 'current_responsible_user_id');
    }

    public function securityNotifications(): HasMany
    {
        return $this->hasMany(SecurityNotification::class)->latest('id');
    }

    public function registeredPorterWithdrawals(): HasMany
    {
        return $this->hasMany(PorterStudentWithdrawal::class, 'registered_by')->latest('withdrawn_at');
    }

    public function authorizedPorterWithdrawals(): HasMany
    {
        return $this->hasMany(PorterStudentWithdrawal::class, 'authorized_by')->latest('withdrawn_at');
    }

    public function porterMovementLogs(): HasMany
    {
        return $this->hasMany(PorterMovementLog::class, 'performed_by')->latest('performed_at');
    }

    public function relevantCalendarResponsibleEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'responsible_user_id')->latest('start_date');
    }

    public function relevantCalendarCreatedEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'created_by')->latest('start_date');
    }

    public function relevantCalendarCompletedEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'completed_by')->latest('completed_at');
    }

    public function relevantCalendarEventLinks(): HasMany
    {
        return $this->hasMany(CalendarEventUser::class)->latest('id');
    }

    public function ownedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'owner_user_id')->latest('updated_at');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by_user_id')->latest('created_at');
    }

    public function taskAssigners(): HasMany
    {
        return $this->hasMany(TaskAssigner::class, 'target_user_id')->latest('id');
    }

    public function taskAssignmentTargets(): HasMany
    {
        return $this->hasMany(TaskAssigner::class, 'assigner_user_id')->latest('id');
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
        if ($this->isSuperAdmin()) {
            return Permission::query()
                ->where('active', true)
                ->pluck('slug')
                ->unique()
                ->values()
                ->all();
        }

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
