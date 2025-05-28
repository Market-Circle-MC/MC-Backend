<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if a user is authenticated
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        // Check if the authenticated user has the 'admin' role
        if ($request->user() && $request->user()->role === 'admin')
        {
        return $next($request);
        }

        // User is authenticated but not an admin
        return response()->json(['message' => 'Unauthorized - Requires admin access'], 403);
    }
}
