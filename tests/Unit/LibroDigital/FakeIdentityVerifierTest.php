<?php

namespace Tests\Unit\LibroDigital;

use App\DTO\LibroDigital\IdentityVerificationData;
use App\Services\LibroDigital\Identity\FakeIdentityVerifier;
use Tests\TestCase;

class FakeIdentityVerifierTest extends TestCase
{
    public function test_fake_verifier_is_deterministic_and_does_not_return_the_otp(): void
    {
        $result = (new FakeIdentityVerifier)->verify(new IdentityVerificationData(
            run: '123456785',
            otp: '123456',
            timestamp: '2026-08-12T12:00:00-04:00',
            correlationId: '01TESTCORRELATION',
            payloadHash: str_repeat('a', 64),
        ));

        $this->assertTrue($result->verified);
        $this->assertSame('verified', $result->status);
        $this->assertStringNotContainsString('123456', json_encode($result, JSON_THROW_ON_ERROR));
    }
}
