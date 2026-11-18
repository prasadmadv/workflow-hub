<?php

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\LoginRateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Tests\TestCase;

class LoginRateLimiterTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
    }

    //Test 1 — Allows attempts below the account limit

    public function test_login_is_allowed_below_account_attempt_limit(): void
    {
        $service = new LoginRateLimiter();

        $email = 'john@example.com';
        $ip = '127.0.0.1';
        for($i = 0; $i < 4; $i++)
        {
            $service->recordFailedAttempt($email, $ip);
        }

        // The fifth login request should still be allowed.
        $service->ensureNotRateLimited($email, $ip);

        $this->expectNotToPerformAssertions();
    }

    // Test 2 — Blocks after five failed attempts
    public function test_login_is_blocked_after_five_account_failures(): void
    {
        $service = new LoginRateLimiter();

        $email = 'john@example.com';
        $ip = '127.0.0.1';
        for($i = 0; $i < 5; $i++)
        {
            $service->recordFailedAttempt($email, $ip);
        }

        // The fifth login request should not be allowed.
        $this->expectException(
            ThrottleRequestsException::class
        );

        $service->ensureNotRateLimited($email, $ip);
    }

    // Test 3 — IP-level protection
    public function test_login_is_blocked_after_thirty_failures_from_same_ip(): void
    {
        $service = new LoginRateLimiter();

        $ip = '127.0.0.1';

        // Record failures using different email addresses.
        for ($i = 1; $i <= 30; $i++) {
            $service->recordFailedAttempt("user{$i}@example.com", $ip);
        }

        $this->expectException(
            ThrottleRequestsException::class
        );

        // A new account from the same IP must be blocked.
        $service->ensureNotRateLimited('another@example.com', $ip);
    }

    // Test 4 — Successful login clears account attempts
    public function test_successful_login_clears_account_attempts(): void
    {
        $service = new LoginRateLimiter();

        $email = 'john@example.com';
        $ip = '127.0.0.1';

        // Record four failed attempts.
        for ($i = 0; $i < 4; $i++) {
            $service->recordFailedAttempt($email, $ip);
        }

        // Simulate successful login cleanup.
        $service->clearAccountAttempts($email);

        // The account should no longer be rate limited.
        $service->ensureNotRateLimited($email, $ip);

        $this->expectNotToPerformAssertions();

    }

    // Test 5 — Clearing account attempts must not clear IP attempts

    public function test_clearing_account_attempts_does_not_clear_ip_attempts(): void
    {
        $service = new LoginRateLimiter();

        $ip = '127.0.0.1';

        // Record 30 failures across different accounts.
        for ($i = 1; $i <= 30; $i++) {
            $service->recordFailedAttempt("user{$i}@example.com", $ip);
        }

        // Clear one account's counter.
        $service->clearAccountAttempts('user1@example.com');

        // A different account from the same IP must still be blocked.
        $this->expectException(
            \Illuminate\Http\Exceptions\ThrottleRequestsException::class
        );

        $service->ensureNotRateLimited('another@example.com', $ip);
    }

}
