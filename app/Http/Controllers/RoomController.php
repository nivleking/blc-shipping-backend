<?php

namespace App\Http\Controllers;

use App\Models\CardTemporary;
use App\Models\Room;
use App\Models\Deck;
use App\Models\ShipBay;
use App\Models\User;
use Illuminate\Http\Request;

class RoomController extends Controller
{
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
            'deck_id' => 'required|exists:decks,id',
            'max_users' => 'required|integer',
            'bay_size' => 'required|array',
            'bay_count' => 'required|integer',
        ]);

        $admin = $request->user();
        $room = Room::create([
            'id' => $validated['id'],
            'admin_id' => $admin->id,
            'name' => $request->input('name', 'Default Room Name'),
            'description' => $request->input('description', 'Default Room Description'),
            'deck_id' => $validated['deck_id'],
            'max_users' => $validated['max_users'],
            'bay_size' => json_encode($validated['bay_size']),
            'bay_count' => $validated['bay_count'],
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

    public function swapBays(Request $request, Room $room)
    {
        $userIds = json_decode($room->users, true);

        if (count($userIds) < 2) {
            return response()->json(['message' => 'Not enough users to swap bays'], 400);
        }

        $shipBays = ShipBay::whereIn('user_id', $userIds)->get()->keyBy('user_id');

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

    public function getDeckOrigins(Room $room)
    {
        $deck = Deck::with('cards')->find($room->deck_id);
        $origins = $deck->cards->pluck('origin')->unique();

        return response()->json($origins);
    }

    public function setPorts(Request $request, Room $room)
    {
        $request->validate([
            'ports' => 'required|array',
            'ports.*' => 'required|string',
        ]);

        $ports = $request->input('ports');

        foreach ($ports as $userId => $port) {
            $user = User::find($userId);

            if ($user) {
                ShipBay::updateOrCreate(
                    ['user_id' => $user->id, 'room_id' => $room->id],
                    ['port' => $port, 'arena' => json_encode([])]
                );
            }
        }

        $shipbays = ShipBay::where('room_id', $room->id)->get();

        return response()->json(
            [
                'message' => 'Ports set successfully',
                'ports' => $ports,
                'shipbays' => $shipbays,
            ],
            200
        );
    }

    public function getBayConfig($roomId)
    {
        $room = Room::findOrFail($roomId);
        return response()->json([
            'baySize' => json_decode($room->bay_size),
            'bayCount' => $room->bay_count,
        ], 200);
    }

    public function getUserPorts(Request $request, $roomId)
    {
        $user = $request->user();
        $shipBay = ShipBay::where('room_id', $roomId)->where('user_id', $user->id)->first();
        return response()->json(['port' => $shipBay->port]);
    }

    public function createCardTemporary(Request $request, Room $room, User $user)
    {
        $validated = $request->validate([
            'card_id' => 'required|exists:cards,id',
        ]);

        $cardTemporary = CardTemporary::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'card_id' => $validated['card_id'],
            'status' => 'selected',
        ]);

        return response()->json($cardTemporary, 201);
    }

    public function acceptCardTemporary(Request $request)
    {
        $validated = $request->validate([
            'card_temporary_id' => 'required|exists:cards,id',
        ]);
        $cardTemporary = CardTemporary::where('card_id', $validated['card_temporary_id'])->first();
        $cardTemporary->status = 'accepted';
        $cardTemporary->save();

        return response()->json(['message' => 'Sales call card accepted.']);
    }

    public function rejectCardTemporary(Request $request)
    {
        $validated = $request->validate([
            'card_temporary_id' => 'required|exists:cards,id',
        ]);
        $cardTemporary = CardTemporary::where('card_id', $validated['card_temporary_id'])->first();
        $cardTemporary->status = 'rejected';
        $cardTemporary->save();

        return response()->json(['message' => 'Sales call card rejected.']);
    }
}
