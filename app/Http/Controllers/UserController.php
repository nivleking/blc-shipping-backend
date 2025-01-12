<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $admin = $request->user();
        if (!$admin->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|unique:users',
            'is_admin' => 'required',
            'password' => 'required|string|confirmed',
        ]);

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|exists:users',
            'password' => 'required'
        ]);

        $user = User::where('name', $request->name)->first();

        if (
            !$user ||
            !Hash::check($request->password, $user->password)
        ) {
            return response()->json([
                'errors' => [
                    'name' => [
                        'The provided credentials are incorrect.',
                    ]
                ]
            ], 401);
        }

        $prefix = $user->is_admin ? 'admin' : 'user';
        $token = $user->createToken("{$prefix}-token")->plainTextToken;
        return response()->json(['token' => $token], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out'], 200);
    }

    // public function sessionLogin(Request $request)
    // {
    //     $credentials = $request->validate([
    //         'name' => 'required|exists:users',
    //         'password' => 'required'
    //     ]);

    //     if (!Auth::attempt($credentials)) {
    //         return response()->json([
    //             'errors' => [
    //                 'name' => [
    //                     'The provided credentials are incorrect.',
    //                 ]
    //             ]
    //         ], 401);
    //     }

    //     $request->session()->regenerate();

    //     return response()->json(['message' => 'Logged in successfully'], 200);
    // }

    // public function sessionLogout(Request $request)
    // {
    //     Auth::guard('web')->logout();

    //     $request->session()->invalidate();
    //     $request->session()->regenerateToken();

    //     return response()->json(['message' => 'Logged out'], 200);
    // }

    public function show(User $user)
    {
        return response()->json($user, 200);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'string|max:255|unique:users',
            'email' => 'string|email|unique:users',
            'password' => 'string|confirmed',
        ]);

        $user->update($validated);

        return response()->json($user, 200);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json("Deleted", 204);
    }

    public function getAllUsers()
    {
        $admins = User::where('is_admin', false)->get();
        return response()->json($admins);
    }

    public function getAllAdmins()
    {
        $admins = User::where('is_admin', true)->get();
        return response()->json($admins);
    }
}
