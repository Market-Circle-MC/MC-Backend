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
        $loginIdentifier = null;
        if ($request->has('email')) {
            $loginIdentifier = 'email';
        } elseif ($request->has('phone_number')) {
            $loginIdentifier = 'phone_number';
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Either email or phone number is required for registration.'
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'role' => $request->input('role', 'customer'),
            'email' => $loginIdentifier === 'email' ? $request->email : null,
            'phone_number' => $loginIdentifier === 'phone_number' ? $request->phone_number : null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
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
        $user = Auth::user();

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user,
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
        $request->user()->tokens()->delete();
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
            'user' =>
            $request->user()->only('id', 'name', 'email', 'phone_number', 'role')
        ], 200);
    }
}