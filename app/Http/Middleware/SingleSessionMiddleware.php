<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\UserSession;
use Carbon\Carbon;

class SingleSessionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $sessionId = session()->getId();
            $userId = $request->user()->id;

            // Update last active timestamp
            UserSession::updateOrCreate(
                ['session_id' => $sessionId],
                [
                    'user_id' => $userId,
                    'last_active_at' => Carbon::now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                ]
            );

            // Check if this is the valid session for this user
            $validSession = UserSession::where('user_id', $userId)
                ->where('session_id', $sessionId)
                ->exists();

            if (!$validSession) {
                // Force logout this user if this isn't their valid session
                auth()->guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->json([
                    'message' => 'Another session is already active for this account.',
                    'active_elsewhere' => true
                ], 401);
            }
        }

        return $next($request);
    }
}
