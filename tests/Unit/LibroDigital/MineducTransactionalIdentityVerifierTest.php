<?php

namespace Tests\Unit\LibroDigital;

use App\DTO\LibroDigital\IdentityVerificationData;
use App\Services\LibroDigital\Identity\MineducTransactionalIdentityVerifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MineducTransactionalIdentityVerifierTest extends TestCase
{
    public function test_it_uses_the_exact_transactional_parameter_names_and_accepts_boolean_json(): void
    {
        config()->set('libro_digital.identity_verifier.transactional_url', 'https://apiede.example.test/otp/verify-otp');
        Http::fake(['apiede.example.test/*' => Http::response('true', 200, ['Content-Type' => 'application/json'])]);

        $result = app(MineducTransactionalIdentityVerifier::class)->verify(new IdentityVerificationData(
            run: '111111111',
            otp: '654321',
            timestamp: '2026-08-12T15:00:00-04:00',
            correlationId: '01TESTCORRELATION1234567890',
            payloadHash: str_repeat('a', 64),
        ));

        $this->assertTrue($result->verified);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['rut'] ?? null) === '111111111'
                && ($query['otp'] ?? null) === '654321'
                && ($query['DateWithTimeZone'] ?? null) === '2026-08-12T15:00:00-04:00'
                && ! array_key_exists('RUN', $query);
        });
    }
}
