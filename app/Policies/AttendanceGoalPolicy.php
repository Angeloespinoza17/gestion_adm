<?php

namespace App\Policies;

use App\Models\Attendance\AttendanceGoal;
use App\Models\User;

class AttendanceGoalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance_statistics.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance_statistics.manage_goals');
    }

    public function update(User $user, AttendanceGoal $goal): bool
    {
        return $user->hasPermission('attendance_statistics.manage_goals');
    }

    public function delete(User $user, AttendanceGoal $goal): bool
    {
        return $user->hasPermission('attendance_statistics.manage_goals');
    }
}
