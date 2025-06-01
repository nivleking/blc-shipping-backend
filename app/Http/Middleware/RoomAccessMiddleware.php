<?php

namespace App\Http\Middleware;

use App\Models\Room;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoomAccessMiddleware
{
    /**
     * Check if user has access to the requested room
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if ($user == null) {
            return response()->json(['message' => 'Unauthenticated', 'user' => $user], 401);
        }

        // Admins bypass the check
        if ($user->is_admin) {
            return $next($request);
        }

        // Get room from route parameter (handling both cases)
        $routeParam = $request->route('room') ?? $request->route('roomId');

        // Already a Room model through route model binding
        if ($routeParam instanceof Room) {
            $room = $routeParam;
        }
        // Simple ID, needs to be found
        else if (is_scalar($routeParam)) {
            $room = Room::find($routeParam);
        }
        // No room parameter found
        else {
            return response()->json(['message' => 'Room not found'], 404);
        }

        // Room doesn't exist
        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $assignedUsers = json_decode($room->assigned_users) ?? [];

        if (!in_array($user->id, $assignedUsers)) {
            return response()->json(['message' => 'You are not assigned to this room'], 403);
        }

        return $next($request);
    }
}
