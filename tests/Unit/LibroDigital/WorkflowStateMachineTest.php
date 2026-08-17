<?php

namespace Tests\Unit\LibroDigital;

use App\Services\LibroDigital\WorkflowStateMachine;
use DomainException;
use PHPUnit\Framework\TestCase;

class WorkflowStateMachineTest extends TestCase
{
    public function test_signed_session_cannot_return_to_draft(): void
    {
        $machine = new WorkflowStateMachine;

        $this->assertTrue($machine->can('session', 'ready_to_sign', 'signing'));
        $this->assertTrue($machine->can('session', 'signing', 'signed'));
        $this->assertFalse($machine->can('session', 'signed', 'draft'));

        $this->expectException(DomainException::class);
        $machine->assertCan('session', 'signed', 'draft');
    }

    public function test_validated_export_can_only_be_released_staled_or_revoked(): void
    {
        $machine = new WorkflowStateMachine;

        $this->assertSame(['released', 'stale', 'revoked'], $machine->next('ede_export', 'validated'));
    }

    public function test_ede_validation_must_pass_through_the_queued_state(): void
    {
        $machine = new WorkflowStateMachine;

        $this->assertTrue($machine->can('ede_export', 'generated', 'validation_queued'));
        $this->assertTrue($machine->can('ede_export', 'validation_queued', 'validating'));
        $this->assertFalse($machine->can('ede_export', 'generated', 'validating'));
        $this->assertFalse($machine->can('ede_export', 'validation_failed', 'validating'));
    }
}
