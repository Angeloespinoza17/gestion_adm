<?php

namespace App\Services\Remuneration;

use App\Models\Remuneration\RemunerationAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RemunerationAuditService
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
        $payload = [
            'action' => $action,
            'user_id' => $user?->id,
            'ip_address' => $request?->ip(),
            'old_values' => $oldValues !== [] ? $oldValues : null,
            'new_values' => $newValues !== [] ? $newValues : null,
            'changes' => $this->diff($oldValues, $newValues),
            'reason' => $reason,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => now(),
        ];

        if ($model) {
            $payload['auditable_type'] = $model->getMorphClass();
            $payload['auditable_id'] = $model->getKey();
        }

        RemunerationAuditLog::query()->create($payload);
    }

    /**
     * @return array<string, array{old:mixed,new:mixed}>|null
     */
    private function diff(array $oldValues, array $newValues): ?array
    {
        $changes = [];
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes !== [] ? $changes : null;
    }
}
