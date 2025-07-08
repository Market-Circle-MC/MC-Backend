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
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle the Google authentication callback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('google_id', $googleUser->id)->first();

            if (!$user) {
                $user = User::where('email', $googleUser->email)->first();

                if ($user) {
                    $user->google_id = $googleUser->id;
                    $user->avatar = $googleUser->avatar;
                    $user->save();
                } else {
                    $user = User::create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar,
                        'email_verified_at' => now(),
                        'password' => Hash::make(Str::random(24)),
                    ]);
                }
            }

            $token = $user->createToken('google-auth-token')->plainTextToken;
            $userRole = $user->role ?? 'customer'; // Assuming 'role' attribute exists on your User model

            // --- IMPORTANT: Redirect to your frontend with token and role ---
            $frontendLoginUrl = env('FRONTEND_URL', 'http://localhost:5173') . '/login';

            // Encode parameters to ensure they are URL-safe
            $redirectUrl = $frontendLoginUrl . '?token=' . urlencode($token) . '&role=' . urlencode($userRole);

            // Force a direct browser redirect using a Response object
            // This explicitly sets the Location header and 302 status code.
            return response()->redirectTo($redirectUrl);

        } catch (\Exception $e) {
            Log::error('Google Auth Error: ' . $e->getMessage());
            $frontendLoginUrl = env('FRONTEND_URL', 'http://localhost:5173') . '/login';
            $errorParam = urlencode('Google authentication failed: ' . $e->getMessage());
            return response()->redirectTo($frontendLoginUrl . '?error=' . $errorParam);
        }
    }
}
