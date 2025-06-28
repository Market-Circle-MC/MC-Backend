<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use Laravel\Sanctum\HasApiTokens;

/**
 * AuthController handles user authentication.
 */
class AuthController extends Controller
{
    /**
     * Register a new user
     * POST /api/register
     */
    public function register(RegisterRequest $request)
    {
        // Removed the $loginIdentifier logic here.
        // The RegisterRequest validation should ensure that either email,
        // phone_number, or both are present and valid as per your rules.

        $user = User::create([
            'name' => $request->input('name'), // Use input() for safety and consistency
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role', 'customer'), // 'customer' as default if not provided
            'email' => $request->input('email'), // Directly use email from request
            'phone_number' => $request->input('phone_number'), // Directly use phone_number from request
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            // Ensure phone_number is included in the returned user data
            'user' => $user->only('id', 'name', 'email', 'phone_number', 'role'),
            'token' => $token,
        ], 201);
    }

    /**
     * Log in a user.
     * POST /api/login
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('password');
        // FIX: Directly use email or phone_number if present in the request for credentials
        if ($request->has('email')) {
            $credentials['email'] = $request->input('email');
        } elseif ($request->has('phone_number')) {
            $credentials['phone_number'] = $request->input('phone_number');
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Either email or phone number is required for login.'
            ], 422);
        }

        // Attempt to authenticate the user
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials.'
            ], 401);
        }

        // If authentication is successful
        /** @var \App\Models\User $user **/
        $user = Auth::user();

        
        $user->tokens()->delete(); // This deletes ALL tokens for the user

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            // Return 'phone_number' here as well, so frontend can receive it
            'user' => $user->only('id', 'name', 'email', 'phone_number', 'role'), 
            'token' => $token
        ], 200);
    }


    /**
     * Log out the authenticated user (revoke current token).
     * POST /api/logout
     * Requires 'auth:sanctum' middleware
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete(); // Delete only the current token
        // Or to delete all tokens for the user:
        // $request->user()->tokens()->delete();
        return response()->json([
            'message' => 'User logged out successfully'
        ]);
    }


    /**
     * Get authenticated user details.
     * GET /api/user
     * Requires 'auth:sanctum' middleware
     */
    public function user(Request $request)
    {
        // Ensure only specific user data is returned, not sensitive fields like password
        return response()->json([
            'status' => 'success',
            'message' => 'User details retrieved successfully',
            // Return 'phone_number' here. Also 'created_at' if frontend uses 'joinedDate' directly.
            'user' => $request->user()->only('id', 'name', 'email', 'phone_number', 'role', 'created_at')
        ], 200);
    }
}
