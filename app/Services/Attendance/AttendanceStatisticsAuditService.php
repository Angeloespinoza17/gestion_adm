<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceStatisticsAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AttendanceStatisticsAuditService
{
    public function log(
        string $action,
        ?Model $model,
        ?User $user,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        ?Request $request = null,
        array $metadata = [],
    ): void {
        $changes = [];
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
            }
        }
        AttendanceStatisticsAuditLog::query()->create([
            'user_id' => $user?->id,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'action' => $action,
            'origin' => 'web',
            'ip_address' => $request?->ip(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'changes' => $changes ?: null,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
