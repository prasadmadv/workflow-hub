<?php

namespace App\Services\Auth;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

class LoginRateLimiter
{
    private const ACCOUNT_MAX_ATTEMPTS = 5;
    private const IP_MAX_ATTEMPTS = 30;
    private const DECAY_SECONDS = 60;

    private function accountKey(string $email): string
    {
        $normalizedEmail = strtolower(trim($email));

        return 'login:account:'.hash('sha256', $normalizedEmail);
    }

    private function ipKey(string $ip): string
    {
        return 'login:ip:'.$ip;
    }

    // checks both limits and rejects requests when necessary
    public function ensureNotRateLimited(string $email, string $ip): void
    {
        $accountKey = $this->accountKey($email);
        $ipKey = $this->ipKey($ip);

        $accountLimited = RateLimiter::tooManyAttempts($accountKey, self::ACCOUNT_MAX_ATTEMPTS);
        $ipLimited = RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS);

        if($accountLimited || $ipLimited){
            $retryAfter = 0;

            if ($accountLimited) {
                $retryAfter = max(
                    $retryAfter,
                    RateLimiter::availableIn($accountKey)
                );
            }

            if ($ipLimited) {
                $retryAfter = max(
                    $retryAfter,
                    RateLimiter::availableIn($ipKey)
                );
            }

            throw new ThrottleRequestsException(
                'Too many login attempts. Please try again later.',
                null,
                [],
                $retryAfter
            );
        }
    }

    // increments both counters
    public function recordFailedAttempt(string $email, string $ip): void
    {
        RateLimiter::hit(
            $this->accountKey($email),
            self::DECAY_SECONDS
        );

        RateLimiter::hit(
            $this->ipKey($ip),
            self::DECAY_SECONDS
        );
    }

    // clears only the account-specific counter
    public function clearAccountAttempts(string $email): void
    {
        RateLimiter::clear($this->accountKey($email));
    }

}
