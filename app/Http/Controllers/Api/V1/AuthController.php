<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use App\Services\Auth\LoginRateLimiter;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {

        // Validate user input
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Create new user
        $user = User::create($validated);

        // Authenticated user
        Auth::guard('web')->login($user);

        // Regenerate user session
        $request->session()->regenerate();

        // return json response
        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
        ], 201);

    }

    public function login(Request $request, LoginRateLimiter $loginRateLimiter): JsonResponse
    {
        // validate user input
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string']
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));
        $ipAddress = $request->ip();

        // Check both rate limits.
        $loginRateLimiter->ensureNotRateLimited($normalizedEmail, $ipAddress);

        // attempt login
        if(!Auth::guard('web')->attempt($validated)){
            $loginRateLimiter->recordFailedAttempt(
                $normalizedEmail,
                $ipAddress
            );
            return response()->json([
                'message' => 'Invalid credentials'
            ], 422);
        }

        // regenerate session
        $request->session()->regenerate();

        $loginRateLimiter->clearAccountAttempts($normalizedEmail);

        // return authenticated users josn response
        return response()->json([
            'message' => 'Login successful',
            'user' => Auth::user()
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout successful'
        ]);
    }

}
