<?php

namespace App\Services\LibroDigital;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AuditEventWriter
{
    public function __construct(private readonly CanonicalJson $canonical) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function write(
        string $eventType,
        string $action,
        Model|string $auditable,
        int|string|null $auditableId = null,
        ?User $actor = null,
        ?int $schoolId = null,
        ?int $academicYearId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?Request $request = null,
        int $entityRevision = 1,
        ?string $correlationId = null,
    ): int {
        if (! $schoolId) {
            throw new InvalidArgumentException('AuditEventWriter requiere un contexto de establecimiento.');
        }

        $auditableType = $auditable instanceof Model ? $auditable::class : $auditable;
        $auditableId ??= $auditable instanceof Model ? $auditable->getKey() : null;
        $correlationId ??= (string) ($request?->attributes->get('lcd_correlation_id') ?: Str::ulid());
        // Second precision is portable across MySQL and the SQLite test suite.
        $occurredAt = Carbon::now('UTC')->format('Y-m-d H:i:s');
        $actorRole = $actor?->roles()->orderBy('roles.id')->value('slug') ?: 'system';
        $diff = $this->diff($before, $after);

        return DB::transaction(function () use (
            $eventType,
            $action,
            $auditableType,
            $auditableId,
            $actor,
            $actorRole,
            $schoolId,
            $academicYearId,
            $before,
            $after,
            $reason,
            $request,
            $entityRevision,
            $correlationId,
            $occurredAt,
            $diff,
        ): int {
            DB::table('lcd_schools')->where('id', $schoolId)->lockForUpdate()->first();
            $previous = DB::table('lcd_audit_events')
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first(['sequence_number', 'event_hash']);
            $previousHash = $previous?->event_hash;
            $sequenceNumber = ((int) ($previous?->sequence_number ?? 0)) + 1;

            $payload = [
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'sequence_number' => $sequenceNumber,
                'actor_user_id' => $actor?->getKey(),
                'actor_staff_id' => $actor?->staff_id,
                'actor_role_snapshot' => $actorRole,
                'impersonator_user_id' => null,
                'break_glass_reason' => $request?->header('X-Break-Glass-Reason'),
                'request_id' => (string) ($request?->header('X-Request-ID') ?: $correlationId),
                'event' => $eventType,
                'action' => $action,
                'auditable_type' => $auditableType,
                'auditable_id' => (int) $auditableId,
                'revision' => $entityRevision,
                'reason' => $reason,
                'before_hash' => $before === null ? null : $this->canonical->hash($before),
                'after_hash' => $after === null ? null : $this->canonical->hash($after),
                'encrypted_diff' => $diff === [] ? null : Crypt::encryptString($this->canonical->encode($diff)),
                'ip_address_encrypted' => $request?->ip() ? Crypt::encryptString((string) $request->ip()) : null,
                'user_agent_hash' => $request?->userAgent() ? hash('sha256', (string) $request->userAgent()) : null,
            ];

            $eventHash = $this->eventHash((string) $previousHash, $payload, $occurredAt, $correlationId);

            return (int) DB::table('lcd_audit_events')->insertGetId([
                'public_id' => (string) Str::ulid(),
                ...$payload,
                'correlation_id' => $correlationId,
                'previous_event_hash' => $previousHash,
                'event_hash' => $eventHash,
                'occurred_at' => $occurredAt,
                'created_at' => now('UTC'),
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function eventHash(string $previousHash, array $payload, string $occurredAt, string $correlationId): string
    {
        return hash('sha256', $previousHash.$this->canonical->encode($payload).$occurredAt.$correlationId);
    }

    /** @return array<string, array{before: mixed, after: mixed}> */
    private function diff(?array $before, ?array $after): array
    {
        $before ??= [];
        $after ??= [];
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        $diff = [];

        foreach ($keys as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $diff[$key] = ['before' => $before[$key] ?? null, 'after' => $after[$key] ?? null];
            }
        }

        return $diff;
    }
}
