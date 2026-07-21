<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiRateLimiterTest extends TestCase
{
    public function test_it_keeps_public_requests_restricted_and_gives_each_authenticated_user_a_separate_limit(): void
    {
        $limiter = RateLimiter::limiter('api');
        $guestRequest = Request::create('/api/students/reports');
        $guestLimit = $limiter($guestRequest);

        $user = new User;
        $user->forceFill(['id' => 123]);
        $authenticatedRequest = Request::create('/api/students/reports');
        $authenticatedRequest->setUserResolver(
            fn (?string $guard = null) => $guard === 'sanctum' ? $user : null,
        );
        $authenticatedLimit = $limiter($authenticatedRequest);

        $this->assertSame(60, $guestLimit->maxAttempts);
        $this->assertSame('ip:127.0.0.1', $guestLimit->key);
        $this->assertSame(300, $authenticatedLimit->maxAttempts);
        $this->assertSame('user:123', $authenticatedLimit->key);
    }
}
