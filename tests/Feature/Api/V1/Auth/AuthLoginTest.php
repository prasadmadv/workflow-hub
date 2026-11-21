<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173',
        ];
    }

    // successful login with valid creds
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'SecurePassword123'
        ]);

        $response = $this->withHeaders($this->spaHeaders())
        ->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePassword123'
        ]);

        $response->assertOk()
        ->assertJson([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'email' => 'john@example.com',
            ]
        ]);

        $this->assertAuthenticatedAs($user);
    }

    // login rejected with incorrect password
    public function test_login_failed_with_incorrect_password(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'SecurePassword123'
        ]);
        $response = $this->withHeaders($this->spaHeaders())
        ->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'IncorrectPassword123'
        ]);

        $response->assertUnprocessable()
        ->assertJson([
            'message' => 'Invalid credentials'
        ]);

        $this->assertGuest();
    }

    // login rejected with incorrect email
    public function test_login_failed_with_incorrect_email(): void
    {
        $response = $this->withHeaders($this->spaHeaders())
        ->postJson('/api/v1/login', [
            'email' => 'john1@example.com',
            'password' => 'SecurePassword123'
        ]);

        $response->assertUnprocessable()
        ->assertJson([
            'message' => 'Invalid credentials'
        ]);

        $this->assertGuest();

    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $email = 'john@example.com';

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->spaHeaders())
                ->postJson('/api/v1/login', [
                    'email' => $email,
                    'password' => 'IncorrectPassword123',
                ])
                ->assertUnprocessable();
        }

        $response = $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/login', [
                'email' => $email,
                'password' => 'SecurePassword123',
            ]);

        $response->assertTooManyRequests();
    }

    public function test_login_is_throttled_after_thirty_failed_attempts_from_same_ip(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->withHeaders($this->spaHeaders())
                ->postJson('/api/v1/login', [
                    'email' => "user{$i}@example.com",
                    'password' => 'IncorrectPassword123',
                ])
                ->assertUnprocessable();
        }

        $response = $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/login', [
                'email' => 'another@example.com',
                'password' => 'SecurePassword123',
            ]);

        $response->assertTooManyRequests();
    }

}
