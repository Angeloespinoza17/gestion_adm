<?php

namespace App\Services\LibroDigital;

use Illuminate\Support\Facades\DB;

class AuditIntegrityVerifier
{
    public function __construct(private readonly AuditEventWriter $writer) {}

    /** @return array{valid: bool, checked: int, errors: array<int, array<string, mixed>>} */
    public function verify(?int $schoolId = null): array
    {
        $errors = [];
        $checked = 0;
        $previousBySchool = [];
        $sequenceBySchool = [];

        DB::table('lcd_audit_events')
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->orderBy('id')
            ->chunkById(500, function ($events) use (&$errors, &$checked, &$previousBySchool, &$sequenceBySchool): void {
                foreach ($events as $event) {
                    $scope = (string) ($event->school_id ?? 'global');
                    $previous = $previousBySchool[$scope] ?? null;
                    $expectedSequence = ($sequenceBySchool[$scope] ?? 0) + 1;
                    $checked++;

                    if ((int) $event->sequence_number !== $expectedSequence) {
                        $errors[] = ['id' => $event->id, 'code' => 'BROKEN_SEQUENCE', 'expected' => $expectedSequence, 'actual' => (int) $event->sequence_number];
                    }

                    if (($event->previous_event_hash ?? null) !== $previous) {
                        $errors[] = ['id' => $event->id, 'code' => 'BROKEN_PREVIOUS_HASH'];
                    }

                    $payload = [
                        'school_id' => $event->school_id,
                        'academic_year_id' => $event->academic_year_id,
                        'sequence_number' => (int) $event->sequence_number,
                        'actor_user_id' => $event->actor_user_id,
                        'actor_staff_id' => $event->actor_staff_id,
                        'actor_role_snapshot' => $event->actor_role_snapshot,
                        'impersonator_user_id' => $event->impersonator_user_id,
                        'break_glass_reason' => $event->break_glass_reason,
                        'request_id' => $event->request_id,
                        'event' => $event->event,
                        'action' => $event->action,
                        'auditable_type' => $event->auditable_type,
                        'auditable_id' => (int) $event->auditable_id,
                        'revision' => (int) $event->revision,
                        'reason' => $event->reason,
                        'before_hash' => $event->before_hash,
                        'after_hash' => $event->after_hash,
                        'encrypted_diff' => $event->encrypted_diff,
                        'ip_address_encrypted' => $event->ip_address_encrypted,
                        'user_agent_hash' => $event->user_agent_hash,
                    ];
                    $occurredAt = $this->databaseTimestampToCanonical((string) $event->occurred_at);
                    $calculated = $this->writer->eventHash((string) $previous, $payload, $occurredAt, (string) $event->correlation_id);

                    if (! hash_equals((string) $event->event_hash, $calculated)) {
                        $errors[] = ['id' => $event->id, 'code' => 'INVALID_EVENT_HASH'];
                    }

                    $previousBySchool[$scope] = $event->event_hash;
                    $sequenceBySchool[$scope] = (int) $event->sequence_number;
                }
            }, 'id');

        return ['valid' => $errors === [], 'checked' => $checked, 'errors' => $errors];
    }

    private function databaseTimestampToCanonical(string $value): string
    {
        // MySQL may return a stored ISO value unchanged; SQLite can preserve it too.
        return $value;
    }
}
