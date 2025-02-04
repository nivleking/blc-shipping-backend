<?php

namespace App\Http\Controllers;

use App\Models\CardTemporary;
use App\Models\Room;
use App\Models\Deck;
use App\Models\ShipBay;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'bay_types' => 'required|array|min:1',
            'bay_types.*' => 'in:dry,reefer',
            'total_rounds' => 'required|integer|min:1',
            'cards_limit_per_round' => 'required|integer|min:1',
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
            'bay_types' => json_encode($validated['bay_types']),
            'total_rounds' => $validated['total_rounds'],
            'cards_limit_per_round' => $validated['cards_limit_per_round'],
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

    public function swapBaysCustom(Request $request, Room $room)
    {
        $request->validate([
            'swapMap' => 'required|array',
            'swapMap.*' => 'required|string'
        ]);

        $swapMap = $request->swapMap;
        $userIds = json_decode($room->users, true);

        Log::info('Received swap map:', $swapMap);

        // Validation checks
        $origins = array_keys($swapMap);
        $destinations = array_values($swapMap);

        // Check for self-swapping
        foreach ($swapMap as $origin => $destination) {
            if ($origin === $destination) {
                return response()->json([
                    'message' => 'Origin and destination cannot be the same',
                    'error' => "Invalid swap: {$origin} → {$destination}"
                ], 400);
            }
        }

        // Check for duplicate destinations
        if (count(array_unique($destinations)) !== count($destinations)) {
            return response()->json([
                'message' => 'Each destination can only be used once',
                'error' => 'Duplicate destinations detected'
            ], 400);
        }

        try {
            // Get all ship bays for users in the room
            $shipBays = ShipBay::whereIn('user_id', $userIds)
                ->where('room_id', $room->id)
                ->get();

            // Create maps for lookup
            $baysByPort = [];
            $originalArenas = [];

            foreach ($shipBays as $bay) {
                $baysByPort[$bay->port] = $bay;
                $originalArenas[$bay->port] = $bay->arena;
            }

            Log::info('Original arenas:', $originalArenas);

            // Perform the swaps
            foreach ($swapMap as $fromPort => $toPort) {
                if (isset($baysByPort[$fromPort]) && isset($originalArenas[$toPort])) {
                    $sourceBay = $baysByPort[$fromPort];
                    $sourceBay->arena = $originalArenas[$toPort];
                    $sourceBay->save();

                    Log::info("Swapped bay {$fromPort} -> {$toPort}");
                } else {
                    Log::warning("Missing bay data for ports: {$fromPort} -> {$toPort}");
                }
            }

            ShipBay::where('room_id', $room->id)
                ->update([
                    'current_round' => DB::raw('current_round + 1'),
                    'current_round_cards' => 0, // Reset cards count for new round
                    'section' => 'section1' // Reset section
                ]);

            return response()->json([
                'message' => 'Bays swapped successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Bay swap error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to swap bays',
                'error' => $e->getMessage()
            ], 500);
        }
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
            'bayTypes' => json_decode($room->bay_types),
        ], 200);
    }

    public function getUserPortsV1(Request $request, $roomId)
    {
        $user = $request->user();
        $shipBay = ShipBay::where('room_id', $roomId)->where('user_id', $user->id)->first();
        return response()->json(['port' => $shipBay->port]);
    }

    public function getUserPortsV2($roomId)
    {
        $shipBays = ShipBay::where('room_id', $roomId)
            ->select('user_id', 'port')
            ->get();

        return response()->json($shipBays);
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

    public function getCardTemporaries($roomId, $userId)
    {
        $cardTemporaries = CardTemporary::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->get();

        return response()->json($cardTemporaries);
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

    public function getUsersRanking($roomId)
    {
        $room = Room::findOrFail($roomId);
        $userIds = json_decode($room->users, true);
        $shipBays = ShipBay::with('user:id,name')
            ->whereIn('user_id', $userIds)
            ->get()
            ->map(function ($shipBay) {
                $shipBay->total_revenue = $shipBay->revenue - $shipBay->penalty;
                return $shipBay;
            });

        $ranking = $shipBays->sortByDesc('total_revenue')->values();

        return response()->json($ranking);
    }
}
