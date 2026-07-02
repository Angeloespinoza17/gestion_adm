<?php

namespace App\Services\Porter;

use App\Models\PorterMovementLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PorterAuditService
{
    public function log(
        Model $loggable,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $description = null,
        ?User $performedBy = null,
        ?Request $request = null,
        array $payload = [],
    ): PorterMovementLog {
        return $loggable->logs()->create([
            'performed_by' => $performedBy?->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'description' => $description,
            'performed_at' => now(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'payload' => $payload === [] ? null : $payload,
        ]);
    }
}
