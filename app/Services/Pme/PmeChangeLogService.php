<?php

namespace App\Services\Pme;

use App\Models\Pme\PmeChangeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PmeChangeLogService
{
    public function record(
        Model $subject,
        string $action,
        ?User $actor = null,
        ?array $before = null,
        ?array $after = null,
        ?string $notes = null,
    ): void {
        PmeChangeLog::query()->create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'before_values' => $before,
            'after_values' => $after,
            'notes' => $notes,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'changed_by' => $actor?->id,
            'changed_at' => now(),
        ]);
    }
}
