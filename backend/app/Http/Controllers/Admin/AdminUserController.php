<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminUserController extends Controller
{
    /**
     * AdminUserController handles administrative user management.
     * This controller is intended for admin-specific user operations.
     * It may include functionalities like listing all users, updating user roles,
     * deleting users, etc.
     */
    // --- IGNORE ---   
    // --- IGNORE ---
    /**
     * Get a list of all users.
     * GET /api/admin/users
     * 
     */
    public function index(Request $request)
    {
        $adminRole = 'admin';
        $user = Auth::user();
        if (!$user || $user->role !== $adminRole) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $users = User::all();
        return response()->json([
            'status' => 'success',
            'message' => 'User list retrieved successfully',
            'data' => $users
        ], 200);
    }
}
