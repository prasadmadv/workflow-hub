<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        // send post request
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173',
        ])->postJson('/api/v1/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
        ]);

        // check if user created, 201
        $response->assertCreated();


        $response->assertJson([
            'message' => 'Registration successful',
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]
        ]);

        // check user in db, persistent data
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // get user by email
        $user = User::where('email', 'john@example.com')->firstOrFail();

        // check authenticated user, valid session
        $this->assertAuthenticatedAs($user);

        // check hashed password match
        $this->assertTrue(
            Hash::check('SecurePassword123', $user->password)
        );
    }

    public function test_user_cannot_register_with_existing_email(): void
    {
        User::factory()->create([
            'email' => 'john@example.com'
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173',
        ])->postJson('/api/v1/register', [
            'name' => 'Another John',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123'
        ]);

        $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);

    }

    public function test_registration_fails_when_password_confirmation_does_not_match(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173',
        ])->postJson('/api/v1/register', [
            'name' => 'Another John',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'DifferentPassword123'
        ]);

        $response->assertUnprocessable()
        ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

}
