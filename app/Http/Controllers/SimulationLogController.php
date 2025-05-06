<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\SimulationLog;
use App\Models\User;
use Illuminate\Http\Request;

class SimulationLogController extends Controller
{
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'room_id' => 'required|exists:rooms,id',
            'arena_bay' => 'required',
            'arena_dock' => 'required',
            'port' => 'required|string',
            'section' => 'required|string',
            'round' => 'required|integer',
            'revenue' => 'required|numeric',
            'penalty' => 'required|numeric',
            'total_revenue' => 'required|numeric',
        ]);

        $simulationLog = SimulationLog::create($validated);

        return response()->json($simulationLog, 201);
    }

    /**
     * Get logs for a specific room
     */
    public function getRoomLogs($roomId)
    {
        $room = Room::find($roomId);
        if (!$room) {
            return response()->json(['error' => 'Room not found'], 404);
        }

        // Get users who participated in this room
        $userIds = SimulationLog::where('room_id', $roomId)
            ->distinct()
            ->pluck('user_id');

        $users = User::whereIn('id', $userIds)->get(['id', 'name']);

        return response()->json([
            'room' => $room,
            'users' => $users
        ], 200);
    }

    /**
     * Get logs for a specific user in a room
     */
    public function getUserLogs(Request $request, $roomId, $userId)
    {
        // Validate inputs
        $validated = $request->validate([
            'section' => 'sometimes|string',
            'round' => 'sometimes|integer',
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $limit = $validated['limit'] ?? 1000;
        $page = $validated['page'] ?? 1;

        // Base query
        $query = SimulationLog::where('room_id', $roomId)
            ->where('user_id', $userId);

        // Apply filters if provided
        if (isset($validated['section'])) {
            $query->where('section', $validated['section']);
        }

        if (isset($validated['round'])) {
            $query->where('round', $validated['round']);
        }

        // Get available rounds and sections for filtering
        $availableRounds = SimulationLog::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('round')
            ->toArray();

        $availableSections = SimulationLog::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('section')
            ->toArray();

        // Get total count for pagination
        $totalCount = $query->count();

        // Get paginated results
        $logs = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'logs' => $logs,
            'pagination' => [
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / $limit)
            ],
            'filters' => [
                'available_rounds' => $availableRounds,
                'available_sections' => $availableSections
            ]
        ], 200);
    }

    public function show(SimulationLog $simulationLog)
    {
        return response()->json($simulationLog);
    }

    public function update(Request $request, SimulationLog $simulationLog)
    {
        //
    }

    public function destroy(SimulationLog $simulationLog)
    {
        //
    }
}
