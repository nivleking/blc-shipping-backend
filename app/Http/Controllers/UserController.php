<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|confirmed',
        ]);

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|exists:users',
            'password' => 'required'
        ]);

        $user = User::where('username', $request->username)->first();

        if (
            !$user ||
            !Hash::check($request->password, $user->password)
        ) {
            return response()->json([
                'errors' => [
                    'username' => [
                        'The provided credentials are incorrect.',
                    ]
                ]
            ], 401);
        }

        $token = $user->createToken('user-token')->plainTextToken;
        return response()->json(['token' => $token], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user instanceof User) {
            $user->tokens()->delete();
            return response()->json(['message' => 'Logged out'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    public function getAllUsers()
    {
        $admins = User::all();
        return response()->json($admins);
    }
}
