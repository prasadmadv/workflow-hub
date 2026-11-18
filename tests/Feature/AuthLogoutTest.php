<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173',
        ];
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
        ]);

        // Authenticate through the actual login endpoint
        $this->withHeaders($this->spaHeaders())
        ->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
        ])->assertOk();

        // Logout
        $response = $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/logout');

        $response->assertOk()
            ->assertJson([
                'message' => 'Logout successful',
            ]);

        $this->assertGuest('web');

    }

    public function test_guest_user_cannot_logout(): void
    {
        $response = $this->withHeaders($this->spaHeaders())
        ->postJson('/api/v1/logout');

        $response->assertUnauthorized();

        $this->assertGuest('web');
    }

}
