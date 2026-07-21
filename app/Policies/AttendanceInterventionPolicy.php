<?php

namespace App\Policies;

use App\Models\Attendance\AttendanceIntervention;
use App\Models\User;

class AttendanceInterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance_statistics.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance_statistics.manage_interventions');
    }

    public function update(User $user, AttendanceIntervention $intervention): bool
    {
        return $user->hasPermission('attendance_statistics.manage_interventions');
    }

    public function delete(User $user, AttendanceIntervention $intervention): bool
    {
        return $user->hasPermission('attendance_statistics.manage_interventions');
    }
}
