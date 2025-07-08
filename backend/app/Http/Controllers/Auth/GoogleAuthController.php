<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;


class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     *
     * This method is called when the frontend initiates Google Sign-In.
     * Socialite will handle generating the Google authentication URL.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToGoogle()
    {
        // Socialite::driver('google')->stateless() is crucial for API-based authentication
        // as it removes the session state check, which is often not used in SPAs/mobile apps.
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle the Google authentication callback.
     *
     * This method is called by Google after the user has authenticated.
     * It receives the user data from Google, finds or creates the user in your DB,
     * and then generates an internal authentication token for your frontend.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            // Retrieve user information from Google.
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Find user by google_id or email
            // Prioritize finding by google_id if available, as it's a unique identifier from Google.
            $user = User::where('google_id', $googleUser->id)->first();

            if (!$user) {
                // If user not found by google_id, check by email.
                // This handles cases where a user might have registered with email/password
                // and then tries to sign in with Google using the same email.
                $user = User::where('email', $googleUser->email)->first();

                if ($user) {
                    // User exists with this email, link their Google ID
                    $user->google_id = $googleUser->id;
                    $user->avatar = $googleUser->avatar;
                    $user->save();
                    // You might want to notify the user that their accounts have been linked.
                } else {
                    // New user, create a new record
                    $user = User::create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar,
                        'email_verified_at' => now(), // Google verifies email, so mark as verified
                        'password' => Hash::make(Str::random(24)), // Generate a random password if needed, or leave null if password login isn't allowed for Google users
                    ]);
                }
            }

            // Log in the user (optional, depending on your API authentication method)
            // If you're using Laravel Sanctum for API tokens, you won't use Auth::login() directly
            // but rather generate a token.
            // Auth::login($user); // This is for web sessions, not typically for API tokens.

            // Generate an API token for the authenticated user (e.g., using Laravel Sanctum)
            // If you are using Sanctum, ensure you have run: php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
            // and php artisan migrate
            $token = $user->createToken('google-auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Google Sign-In successful!',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 200);

        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Google Auth Error: ' . $e->getMessage());
            // Return a generic error message to the frontend
            return response()->json(['message' => 'Google authentication failed. Please try again.'], 500);
        }
    }
}
