<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SanctumSpaAuthenticationTest extends TestCase
{
    public function test_csrf_cookie_endpoint_issues_csrf_and_session_cookies(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertNoContent();

        $response->assertCookie('XSRF-TOKEN');
        $response->assertCookie(config('session.cookie'));
    }

    public function test_unauthenticated_user_cannot_access_protected_api_route(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}
