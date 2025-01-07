<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Admin;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;

class RoomController extends Controller
{

    public function getRoomUsers(Request $request, Room $room)
    {
        $userIds = json_decode($room->users, true);
        $users = User::whereIn('id', $userIds)->get();

        return response()->json($users);
    }

    public function index(Request $request)
    {
        $admin = $request->user();
        if (!$admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $rooms = Room::all();
        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        if (!$admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'id' => 'required|string|unique:rooms',
            'name' => 'string',
            'description' => 'string',
        ]);

        $room = Room::create([
            'id' => $validated['id'],
            'admin_id' => $admin->id,
            'name' => $request->input('name', 'Default Room Name'),
            'description' => $request->input('description', 'Default Room Description'),
            'users' => json_encode([]),
        ]);

        return response()->json($room, 200);
    }

    public function show(Room $room)
    {
        return response()->json($room);
    }

    public function update(Request $request, Room $room)
    {
        $admin = $request->user();
        if (!$admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'string',
            'description' => 'string',
        ]);

        $room->update($validated);

        return response()->json($room);
    }

    public function destroy(Request $request, Room $room)
    {
        $admin = $request->user();
        if (!$admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $room->delete();
        return response()->json(
            ['message' => 'Room deleted successfully'],
            200
        );
    }

    public function joinRoom(Request $request, Room $room)
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $users = json_decode($room->users, true);
        $users[] = $user->id;
        $room->users = json_encode($users);
        $room->save();

        return response()->json($room);
    }

    public function kickUser(Request $request, Room $room, User $user)
    {
        $admin = $request->user();
        if (!$admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $users = json_decode($room->users, true);
        if (($key = array_search($user->id, $users)) !== false) {
            unset($users[$key]);
            $room->users = json_encode(array_values($users));
            $room->save();
        }

        return response()->json($room);
    }
}
