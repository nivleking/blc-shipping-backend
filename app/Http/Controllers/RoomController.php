<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Admin;
use App\Models\Room;
use App\Models\ShipBay;
use App\Models\User;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function swapBays(Request $request, Room $room)
    {
        // Retrieve the list of user IDs in the room
        $userIds = json_decode($room->users, true);

        if (count($userIds) < 2) {
            return response()->json(['message' => 'Not enough users to swap bays'], 400);
        }

        // Retrieve the ship bays for each user in the room
        $shipBays = ShipBay::whereIn('user_id', $userIds)->get()->keyBy('user_id');

        // Swap the arena data between users
        $firstUserId = $userIds[0];
        $lastUserId = $userIds[count($userIds) - 1];
        $firstUserBay = $shipBays[$firstUserId];
        $lastUserBay = $shipBays[$lastUserId];

        $tempArena = $firstUserBay->arena;
        for ($i = 0; $i < count($userIds) - 1; $i++) {
            $shipBays[$userIds[$i]]->arena = $shipBays[$userIds[$i + 1]]->arena;
            $shipBays[$userIds[$i]]->save();
        }
        $lastUserBay->arena = $tempArena;
        $lastUserBay->save();

        return response()->json(['message' => 'Bays swapped successfully']);
    }

    public function getRoomUsers(Request $request, Room $room)
    {
        $userIds = json_decode($room->users, true);
        $users = User::whereIn('id', $userIds)->get();

        return response()->json($users);
    }

    public function index()
    {
        $rooms = Room::all();
        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|unique:rooms',
            'name' => 'string',
            'description' => 'string',
        ]);

        $admin = $request->user();
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
        if ($request->has('status')) {
            $room->status = $request->status;
            $room->save();
            return response()->json($room);
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
        $room->delete();
        return response()->json(
            ['message' => 'Room deleted successfully'],
            200
        );
    }

    public function joinRoom(Request $request, Room $room)
    {
        $user = $request->user();
        $users = json_decode($room->users, true);
        $users[] = $user->id;
        $room->users = json_encode($users);
        $room->save();

        return response()->json($room);
    }

    public function leaveRoom(Request $request, Room $room)
    {
        $user = $request->user();
        $users = json_decode($room->users, true);
        if (($key = array_search($user->id, $users)) !== false) {
            unset($users[$key]);
            $room->users = json_encode(array_values($users));
            $room->save();
        }

        return response()->json($room);
    }

    public function kickUser(Request $request, Room $room, User $user)
    {
        $users = json_decode($room->users, true);
        if (($key = array_search($user->id, $users)) !== false) {
            unset($users[$key]);
            $room->users = json_encode(array_values($users));
            $room->save();
        }

        return response()->json($room);
    }
}
