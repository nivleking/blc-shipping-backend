<?php

namespace App\Http\Controllers;

use App\Models\Room;
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

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users',
            'is_admin' => 'required|boolean',
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/'
            ],
        ], [
            'password.regex' => 'Password must contain at least one letter, one number and one special character'
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

        $validated['created_by'] = $admin->id;
        $validated['updated_by'] = $admin->id;
        $validated['email'] = $email;
        $validated['password_plain'] = $validated['password'];
        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = 'active';

        $user = User::create($validated);

        return response()->json($user->load(['creator', 'editor']), 200);
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

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'login_count' => $user->login_count + 1,
            'status' => 'active'
        ]);

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
            'name' => 'string|max:255|unique:users,name,' . $user->id,
            'password' => [
                'nullable',
                'string',
                'confirmed',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/'
            ],
        ], [
            'password.regex' => 'Password must contain at least one letter, one number and one special character'
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

        $validated['updated_by'] = $request->user()->id;

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password_plain'] = $validated['password'];
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->load(['creator', 'editor']), 200);
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

    public function getUsers(Request $request)
    {
        $isAdmin = $request->query('is_admin', null);

        if ($isAdmin !== null) {
            $isAdmin = filter_var($isAdmin, FILTER_VALIDATE_BOOLEAN);
        }

        $query = User::query()->with(['creator', 'editor'])->latest();

        if ($isAdmin !== null) {
            $query->where('is_admin', $isAdmin);
        }

        $users = $query->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'is_admin' => $user->is_admin,
                'is_super_admin' => $user->is_super_admin,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'created_by' => $user->creator ? [
                    'id' => $user->creator->id,
                    'name' => $user->creator->name
                ] : null,
                'updated_by' => $user->editor ? [
                    'id' => $user->editor->id,
                    'name' => $user->editor->name
                ] : null,
                'last_login_at' => $user->last_login_at,
                'last_login_ip' => $user->last_login_ip,
                'login_count' => $user->login_count,
                'password_plain' => $user->password_plain
            ];
        });

        return response()->json($users);
    }

    public function getAllUsers(Request $request)
    {
        $request->query->set('is_admin', false);
        return $this->getUsers($request);
    }

    public function getAllAdmins(Request $request)
    {
        $request->query->set('is_admin', true);
        return $this->getUsers($request);
    }

    public function showPassword(Request $request, User $user)
    {
        $superAdmin = $request->user();

        if (!$superAdmin->is_super_admin) {
            return response()->json([
                'message' => 'Only Super Admin can view passwords'
            ], 403);
        }

        $validated = $request->validate([
            'super_admin_password' => 'required|string'
        ]);

        if (!Hash::check($validated['super_admin_password'], $superAdmin->password)) {
            return response()->json([
                'message' => 'Invalid super admin password'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'password' => $user->password_plain
        ]);
    }
    public function getUserRooms($userId)
    {
        $user = User::findOrFail($userId);

        $rooms = Room::where(function ($query) use ($userId) {
            // Pattern for finding user ID in arrays like [1,2,3] or [1, 2, 3]
            $patterns = [
                '[' . $userId . ',',
                ',' . $userId . ',',
                ',' . $userId . ']',
                '[' . $userId . ']',
            ];

            foreach ($patterns as $pattern) {
                $query->orWhere('users', 'LIKE', '%' . $pattern . '%')
                    ->orWhere('assigned_users', 'LIKE', '%' . $pattern . '%');
            }
        })
            ->where('status', 'finished')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'rooms' => $rooms
        ]);
    }
}
