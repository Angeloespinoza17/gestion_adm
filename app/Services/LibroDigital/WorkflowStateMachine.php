<?php

namespace App\Services\LibroDigital;

use DomainException;

class WorkflowStateMachine
{
    private const TRANSITIONS = [
        'book' => [
            'draft' => ['pending_preflight'],
            'pending_preflight' => ['draft', 'open'],
            'open' => ['temporarily_locked', 'closing'],
            'temporarily_locked' => ['open', 'closing'],
            'closing' => ['open', 'closed'],
            'closed' => ['open', 'archived'],
            'archived' => [],
        ],
        'session' => [
            'scheduled' => ['draft', 'cancelled'],
            'draft' => ['attendance_in_progress', 'ready_to_sign', 'cancelled'],
            'attendance_in_progress' => ['draft', 'ready_to_sign', 'cancelled'],
            'ready_to_sign' => ['draft', 'signing', 'cancelled'],
            'signing' => ['ready_to_sign', 'signed'],
            'signed' => ['amended', 'closed'],
            'amended' => ['ready_to_sign', 'closed'],
            'cancelled' => ['amended'],
            'closed' => ['amended'],
        ],
        'amendment' => [
            'requested' => ['under_review', 'cancelled'],
            'under_review' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['applied', 'cancelled'],
            'rejected' => [],
            'applied' => [],
            'cancelled' => [],
        ],
        'ede_export' => [
            'requested' => ['preflight_running'],
            'preflight_running' => ['preflight_failed', 'projecting'],
            'preflight_failed' => ['preflight_running', 'revoked'],
            'projecting' => ['projected', 'preflight_failed'],
            'projected' => ['packaging', 'stale'],
            'packaging' => ['generated', 'validation_failed'],
            'generated' => ['validation_queued', 'stale'],
            'validation_queued' => ['validating', 'validation_failed', 'stale'],
            'validating' => ['validated', 'validation_failed'],
            'validation_failed' => ['validation_queued', 'revoked', 'stale'],
            'validated' => ['released', 'stale', 'revoked'],
            'released' => ['stale', 'revoked'],
            'stale' => ['revoked'],
            'revoked' => [],
        ],
    ];

    public function can(string $workflow, string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$workflow][$from] ?? [], true);
    }

    public function assertCan(string $workflow, string $from, string $to): void
    {
        if (! $this->can($workflow, $from, $to)) {
            throw new DomainException("Transicion {$workflow} no permitida: {$from} -> {$to}.");
        }
    }

    /** @return array<int, string> */
    public function next(string $workflow, string $from): array
    {
        return self::TRANSITIONS[$workflow][$from] ?? [];
    }
}
