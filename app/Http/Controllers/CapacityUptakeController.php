<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCapacityUptakeRequest;
use App\Http\Requests\UpdateCapacityUptakeRequest;
use App\Models\CapacityUptake;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CapacityUptakeController extends Controller
{
    /**
     * Get capacity uptake data for a specific room, user, and week.
     */
    public function getByRoomUserWeek($roomId, $userId, $week)
    {
        $capacityUptake = CapacityUptake::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('week', $week)
            ->first();

        if (!$capacityUptake) {
            return response()->json([
                'message' => 'Capacity uptake data not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Capacity uptake data retrieved successfully',
            'data' => $capacityUptake
        ]);
    }

    /**
     * Save or update capacity uptake data.
     */
    public function saveOrUpdate(Request $request)
    {
        $request->validate([
            'room_id' => 'required|string|exists:rooms,id',
            'user_id' => 'required|exists:users,id',
            'week' => 'required|integer|min:1',
            'capacity_data' => 'required|array',
            'sales_calls_data' => 'nullable|array'
        ]);

        $capacityUptake = CapacityUptake::updateOrCreate(
            [
                'room_id' => $request->room_id,
                'user_id' => $request->user_id,
                'week' => $request->week
            ],
            [
                'capacity_data' => $request->capacity_data,
                'sales_calls_data' => $request->sales_calls_data
            ]
        );

        return response()->json([
            'message' => 'Capacity uptake data saved successfully',
            'data' => $capacityUptake
        ]);
    }

    /**
     * Get all capacity uptake weeks for a user in a room.
     */
    public function getWeeksByRoomUser($roomId, $userId)
    {
        $weeks = CapacityUptake::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->orderBy('week')
            ->pluck('week')
            ->toArray(); // Convert to array for consistent response

        // Always return a 200 response, even if no data found
        return response()->json([
            'message' => count($weeks) > 0 ? 'Capacity uptake weeks retrieved successfully' : 'No capacity uptake data found yet',
            'data' => $weeks
        ]);
    }
}
