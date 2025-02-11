<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $admin = $request->user();

        // Hanya super admin yang bisa membuat admin baru
        if (!$admin->is_super_admin && $request->input('is_admin')) {
            return response()->json([
                'message' => 'Only Super Admin can create new admins'
            ], 403);
        }

        // Pastikan is_super_admin tidak bisa diset melalui request
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users',
            'is_admin' => 'required|boolean',
            'password' => 'required|string|confirmed',
        ]);
        $validated['is_super_admin'] = false; // Force set to false for new users

        // Generate unique random email
        $baseEmail = strtolower(str_replace(' ', '', $validated['name']));
        $randomString = substr(md5(uniqid()), 0, 8);
        $email = $baseEmail . $randomString . '@blc-shipping.com';

        // Ensure email is unique
        while (User::where('email', $email)->exists()) {
            $randomString = substr(md5(uniqid()), 0, 8);
            $email = $baseEmail . $randomString . '@blc-shipping.com';
        }

        $validated['email'] = $email;

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    public function refreshToken(Request $request)
    {
        $refreshToken = $request->bearerToken();
        $token = $request->user()->tokens()->where('id', $refreshToken)->first();

        if (
            !$token ||
            $token->expires_at <= Carbon::now()
        ) {
            return response()->json(['message' => 'Refresh token expired or invalid'], 401);
        }

        $token->delete();

        $accessToken = $request->user()->createToken(
            'access_token',
            ['access-api'],
            Carbon::now()->addMinutes(config('sanctum.ac_expiration'))
        );

        $newRefreshToken = $request->user()->createToken(
            'refresh_token',
            ['issue-access-token'],
            Carbon::now()->addMinutes(config('sanctum.rt_expiration'))
        );

        return response()->json([
            'token' => $accessToken->plainTextToken,
            'refresh_token' => $newRefreshToken->plainTextToken,
        ], 200);
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

        $accessToken = $user->createToken(
            'access_token',
            ['access-api'],
            Carbon::now()->addMinutes(config('sanctum.ac_expiration'))
        );
        $refreshToken = $user->createToken(
            'refresh_token',
            ['issue-access-token'],
            Carbon::now()->addMinutes(config('sanctum.rt_expiration'))
        );

        return response()->json([
            'is_admin' => $user->is_admin,
            'token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
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
            'name' => 'string|max:255|unique:users,name,' . $user->id,  // Tambahkan pengecualian untuk ID saat ini
            'password' => 'string|confirmed|nullable',  // Buat password opsional
        ]);

        // Hanya update email jika nama berubah
        if (isset($validated['name']) && $validated['name'] !== $user->name) {
            $baseEmail = strtolower(str_replace(' ', '', $validated['name']));
            $randomString = substr(md5(uniqid()), 0, 8);
            $email = $baseEmail . $randomString . '@blc-shipping.com';

            while (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
                $randomString = substr(md5(uniqid()), 0, 8);
                $email = $baseEmail . $randomString . '@blc-shipping.com';
            }

            $validated['email'] = $email;
        }

        // Hanya update password jika diisi
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user, 200);
    }

    public function destroy(User $user)
    {
        $requestingUser = request()->user();

        // Jika user yang akan dihapus adalah super admin, tolak request
        if ($user->is_super_admin) {
            return response()->json([
                'message' => 'Super Admin cannot be deleted'
            ], 403);
        }

        // Jika yang request bukan super admin dan mencoba menghapus admin, tolak
        if (!$requestingUser->is_super_admin && $user->is_admin) {
            return response()->json([
                'message' => 'Only Super Admin can delete other admins'
            ], 403);
        }

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
