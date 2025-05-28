<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

/**
 * AuthController handles user authentication.
 */
class AuthController extends Controller
{
    /**
     * Register a new user
     * POST /api/register
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'phone_number' => ['nullable', 'string', 'max:15', 'unique:users'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'role' => 'customer', // Default role for new registrations
        ]);

        // Generate API token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user->only('id', 'name', 'email', 'phone_number', 'role'), // Corrected: use ->only()
            'token' => $token,
        ], 201);
    }

    /**
     * Log in a user.
     * POST /api/login
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        }

        // Attempt to authenticate using email and password
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401); // Unauthorized
        }

        $user = Auth::user();

        // Revoke all existing tokens for this user for security (optional, but good practice)
        $user->tokens()->delete();

        // Generate a new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->only('id', 'name', 'email', 'phone_number', 'role'), // Include specific user data
            'token' => $token,
        ]); // 200 OK by default
    }

    /**
     * Log out the authenticated user (revoke current token).
     * POST /api/logout
     * Requires 'auth:sanctum' middleware
     */
    public function logout(Request $request)
    {
        // Delete the current token being used
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get authenticated user details.
     * GET /api/user
     * Requires 'auth:sanctum' middleware
     */
    public function user(Request $request)
    {
        // Ensure only specific user data is returned, not sensitive fields like password
        return response()->json($request->user()->only('id', 'name', 'email', 'phone_number', 'role'));
    }
}