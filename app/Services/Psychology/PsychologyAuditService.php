<?php

namespace App\Services\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PsychologyAuditService
{
    private const REDACTED = [
        'observed_facts',
        'internal_decision_note',
        'private_note',
        'professional_rationale',
        'content',
        'private_path',
        'information_response',
        'general_reason',
        'categories',
        'objectives',
        'next_action',
    ];

    public function record(string $action, Model $model, ?User $user, array $old = [], array $new = [], ?string $reason = null): void
    {
        DB::table('psychology_audit_events')->insert([
            'user_id' => $user?->id, 'action' => $action, 'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(), 'ip_address' => request()?->ip(),
            'old_values' => $this->safeJson($old), 'new_values' => $this->safeJson($new),
            'reason' => $reason, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function safeJson(array $values): ?string
    {
        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[PROTEGIDO]';
            }
        }

        return $values === [] ? null : json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
