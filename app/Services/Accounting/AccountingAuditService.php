<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AccountingAuditService
{
    public function log(
        string $action,
        ?Model $model,
        ?User $user,
        array $oldValues = [],
        array $newValues = [],
        ?string $notes = null,
        ?Request $request = null,
    ): void {
        $payload = [
            'action' => $action,
            'user_id' => $user?->id,
            'ip_address' => $request?->ip(),
            'old_values' => $oldValues !== [] ? $oldValues : null,
            'new_values' => $newValues !== [] ? $newValues : null,
            'notes' => $notes,
        ];

        if ($model) {
            $payload['auditable_type'] = $model->getMorphClass();
            $payload['auditable_id'] = $model->getKey();
        }

        AccountingAuditLog::query()->create($payload);
    }
}
