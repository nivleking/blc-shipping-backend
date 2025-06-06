<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserOwnershipMiddleware
{
    /**
     * Ensure user can only modify their own data.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admins bypass this check
        if ($user && $user->is_admin) {
            return $next($request);
        }

        // Check if user_id is in the request
        $requestUserId = $request->input('user_id');

        // If no user_id in request but we have one in route, use that
        if (!$requestUserId && $request->route('userId')) {
            $requestUserId = $request->route('userId');
        }

        // If still no user_id, let the controller handle it
        if (!$requestUserId) {
            return $next($request);
        }

        // Compare request user_id with authenticated user
        if ((int)$requestUserId !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to modify another user\'s data'
            ], 403);
        }

        return $next($request);
    }
}
