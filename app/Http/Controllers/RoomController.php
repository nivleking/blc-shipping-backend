<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardTemporary;
use App\Models\Container;
use App\Models\Room;
use App\Models\Deck;
use App\Models\ShipBay;
use App\Models\ShipLayout;
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
            'name' => 'required|string',
            'description' => 'required|string',
            'deck' => 'required|exists:decks,id',
            'max_users' => 'required|integer',
            'assigned_users' => 'required|array|min:1',
            'assigned_users.*' => 'exists:users,id',
            'ship_layout' => 'required|exists:ship_layouts,id',
            'total_rounds' => 'required|integer|min:1',
            'cards_limit_per_round' => 'required|integer|min:1',
            'cards_must_process_per_round' => 'required|integer|min:1',
            'swap_config' => 'nullable|array'
        ]);

        $admin = $request->user();
        $layout = ShipLayout::findOrFail($validated['ship_layout']);

        try {
            $room = Room::create([
                'id' => $validated['id'],
                'admin_id' => $admin->id,
                'name' => $validated['name'],
                'description' => $validated['description'],
                'deck_id' => $validated['deck'],
                'ship_layout_id' => $validated['ship_layout'],
                'max_users' => $validated['max_users'],
                'users' => json_encode([]),
                'assigned_users' => json_encode($validated['assigned_users']),
                'bay_size' => json_encode($layout->bay_size),
                'bay_count' => $layout->bay_count,
                'bay_types' => json_encode($layout->bay_types),
                'total_rounds' => $validated['total_rounds'],
                'cards_limit_per_round' => $validated['cards_limit_per_round'],
                'cards_must_process_per_round' => $validated['cards_must_process_per_round'],
                'swap_config' => json_encode($validated['swap_config'] ?? [])
            ]);

            return response()->json($room, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create room',
                'error' => $e->getMessage()
            ], 500);
        }
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
            'total_rounds' => 'integer|min:1',
            'cards_limit_per_round' => 'integer|min:1',
            'assigned_users' => 'array',
            'assigned_users.*' => 'exists:users,id',
            'deck' => 'exists:decks,id',
            'ship_layout' => 'exists:ship_layouts,id',
        ]);

        $room->name = $validated['name'];
        $room->description = $validated['description'];
        $room->total_rounds = $validated['total_rounds'];
        $room->cards_limit_per_round = $validated['cards_limit_per_round'];

        if (isset($validated['assigned_users'])) {
            $room->assigned_users = json_encode($validated['assigned_users']);
        }

        if (isset($validated['deck'])) {
            $room->deck_id = $validated['deck'];
        }

        if (isset($validated['ship_layout'])) {
            $layout = ShipLayout::findOrFail($validated['ship_layout']);
            $room->ship_layout_id = $validated['ship_layout'];
            $room->bay_size = json_encode($layout->bay_size);
            $room->bay_count = $layout->bay_count;
            $room->bay_types = json_encode($layout->bay_types);
        }

        $room->save();

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
        $assignedUsers = json_decode($room->assigned_users, true) ?? [];

        if (!in_array($user->id, $assignedUsers)) {
            return response()->json([
                'message' => 'You are not authorized to join this room'
            ], 403);
        }

        $users = json_decode($room->users, true) ?? [];

        // Check if user is already in the room
        if (in_array($user->id, $users)) {
            return response()->json([
                'message' => 'You are already in this room'
            ], 400);
        }

        // Check if room is full
        if (count($users) >= $room->max_users) {
            return response()->json([
                'message' => 'Room is full'
            ], 400);
        }

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

        $shipBays = ShipBay::whereIn('user_id', $userIds)
            ->where('room_id', $room->id)
            ->get();

        // Get the swap configuration
        $swapConfig = json_decode($room->swap_config, true);

        if (empty($swapConfig)) {
            return response()->json(['message' => 'No swap configuration found'], 400);
        }

        // Create maps for lookup
        $baysByPort = [];
        $originalArenas = [];

        foreach ($shipBays as $bay) {
            $baysByPort[$bay->port] = $bay;
            $originalArenas[$bay->port] = $bay->arena;
        }

        // Perform the swaps according to the configuration
        foreach ($swapConfig as $fromPort => $toPort) {
            if (isset($baysByPort[$fromPort]) && isset($originalArenas[$toPort])) {
                $sourceBay = $baysByPort[$fromPort];
                $sourceBay->arena = $originalArenas[$toPort];
                $sourceBay->save();
            }
        }

        // Increment round counter for all ship bays in this room
        ShipBay::where('room_id', $room->id)
            ->update([
                'processed_cards' => 0,
                'current_round' => DB::raw('current_round + 1'),
                'current_round_cards' => 0, // Reset cards count for new round
                'section' => 'section1' // Reset section
            ]);

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

        $users = User::whereIn('id', $userIds)
            ->distinct()
            ->get();

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
        $validated = $request->validate([
            'ports' => 'present|array|min:1',
            'ports.*' => 'required|string',
        ]);

        $ports = $validated['ports'];
        if (empty($ports)) {
            return response()->json([
                'message' => 'Please assign ports for all users.'
            ], 422);
        }

        $shipBays = [];

        // Get room configuration for bay setup
        $baySize = json_decode($room->bay_size, true);
        $bayCount = $room->bay_count;
        $bayTypes = json_decode($room->bay_types, true);

        // Default values if not set
        if (!$baySize) $baySize = ['rows' => 4, 'columns' => 5];
        if (!$bayCount) $bayCount = 3;
        if (!$bayTypes) $bayTypes = ['dry', 'dry', 'reefer'];

        foreach ($ports as $userId => $port) {
            $user = User::find($userId);

            if ($user) {
                // Create empty arena
                $arena = array_fill(
                    0,
                    $bayCount,
                    array_fill(
                        0,
                        $baySize['rows'],
                        array_fill(0, $baySize['columns'], null)
                    )
                );

                // Populate with initial containers
                $arena = $this->generateInitialContainers($arena, $port, $ports, $bayTypes);

                // Create or update ShipBay
                $shipBay = ShipBay::updateOrCreate(
                    ['user_id' => $user->id, 'room_id' => $room->id],
                    [
                        'port' => $port,
                        'arena' => json_encode($arena),
                        'revenue' => 0,
                        'section' => 'section1',
                        'penalty' => 0,
                        'discharge_moves' => 0,
                        'load_moves' => 0,
                        'accepted_cards' => 0,
                        'rejected_cards' => 0,
                        'current_round' => 1,
                        'current_round_cards' => 0,
                    ]
                );

                $shipBays[] = $shipBay;
            }
        }

        return response()->json(
            [
                'message' => 'Ports set successfully',
                'ports' => $ports,
                'shipbays' => $shipBays,
            ],
            200
        );
    }

    private function generateInitialContainers($arena, $userPort, $allPorts, $bayTypes)
    {
        // Calculate overall dimensions
        $bayCount = count($arena);
        $rowCount = count($arena[0]);
        $colCount = count($arena[0][0]);
        $bottomRowIndex = $rowCount - 1;

        $containersPerPort = 5;

        // Get room and deck information
        $room = Room::find(request()->route('room')->id);
        if (!$room) {
            Log::warning("Room not found for user port: $userPort");
            return $arena;
        }

        // Get valid ports from deck
        $validPorts = [];
        $deck = Deck::find($room->deck_id);
        if ($deck) {
            $originPorts = $deck->cards()->distinct('origin')->pluck('origin')->toArray();
            $destPorts = $deck->cards()->distinct('destination')->pluck('destination')->toArray();
            $validPorts = array_values(array_unique(array_merge($originPorts, $destPorts)));
            $validPorts = array_filter($validPorts, function ($port) {
                return !empty($port);
            });
        }

        // // Add fallback ports if needed
        // if (count($validPorts) < 3) {
        //     $validPorts = array_merge($validPorts, array_values($allPorts));
        //     $validPorts = array_unique($validPorts);
        //     if (count($validPorts) < 3) {
        //         $defaultPorts = ['SBY', 'MKS', 'MDN', 'JYP', 'BPN', 'BKS'];
        //         foreach ($defaultPorts as $port) {
        //             if (!in_array($port, $validPorts)) {
        //                 $validPorts[] = $port;
        //             }
        //         }
        //     }
        // }

        // Create a list of ports excluding user's port
        $otherPorts = array_values(array_diff($validPorts, [$userPort]));

        // // If we don't have enough ports, add some default ones
        // while (count($otherPorts) < 3) {
        //     $defaultPorts = ['SBY', 'MKS', 'MDN', 'JYP', 'BPN', 'BKS'];
        //     foreach ($defaultPorts as $port) {
        //         if (!in_array($port, $otherPorts) && $port !== $userPort) {
        //             $otherPorts[] = $port;
        //             break;
        //         }
        //     }
        // }

        // Limit to 4 ports max (including user port)
        if (count($otherPorts) > 3) {
            $otherPorts = array_slice($otherPorts, 0, 3);
        }

        // Prepare container distribution
        $portsToPlace = array_merge([$userPort], $otherPorts);

        // Prepare container ID counter
        $nextId = 1;
        while (Card::where('id', (string)$nextId)->exists()) {
            $nextId++;
        }

        // Create a map to track containers we've placed for each port
        $placedCounts = [];
        foreach ($portsToPlace as $port) {
            $placedCounts[$port] = 0;
        }

        // Define available positions - only bottom row first
        $bottomPositions = [];
        for ($bay = 0; $bay < $bayCount; $bay++) {
            for ($col = 0; $col < $colCount; $col++) {
                $bottomPositions[] = [
                    'bay' => $bay,
                    'row' => $bottomRowIndex,
                    'col' => $col
                ];
            }
        }

        // Shuffle bottom positions
        shuffle($bottomPositions);

        // Step 1: Place containers in the bottom row first
        foreach ($bottomPositions as $position) {
            // If all ports have enough containers, stop
            $allPortsFilled = true;
            foreach ($portsToPlace as $port) {
                if ($placedCounts[$port] < $containersPerPort) {
                    $allPortsFilled = false;
                    break;
                }
            }

            if ($allPortsFilled) {
                break;
            }

            // Find a port that needs more containers
            $portToPlace = null;
            foreach ($portsToPlace as $port) {
                if ($placedCounts[$port] < $containersPerPort) {
                    $portToPlace = $port;
                    break;
                }
            }

            if (!$portToPlace) {
                continue;
            }

            $bay = $position['bay'];
            $row = $position['row'];
            $col = $position['col'];

            // Skip if position already has a container
            if ($arena[$bay][$row][$col] !== null) {
                continue;
            }

            // Determine container properties
            $destinationPort = $portToPlace;
            $isForUserPort = $destinationPort === $userPort;
            $originPort = $isForUserPort ? $otherPorts[array_rand($otherPorts)] : $userPort;

            // Determine container type
            $bayType = $bayTypes[$bay] ?? 'dry';
            $containerType = $bayType === 'reefer' ? 'reefer' : 'dry';

            // Create the container
            $cardId = (string)$nextId++;
            $revenue = rand(5000000, 15000000);
            $priority = rand(1, 10) <= 5 ? "Committed" : "Non-Committed";

            try {
                $card = Card::create([
                    'id' => $cardId,
                    'type' => $containerType,
                    'priority' => $priority,
                    'origin' => $originPort,
                    'destination' => $destinationPort,
                    'quantity' => 1,
                    'revenue' => $revenue,
                    'is_initial' => true,
                    'generated_for_room_id' => $room->id
                ]);

                $container = Container::create([
                    'color' => $this->generateContainerColor($destinationPort),
                    'card_id' => $cardId,
                    'type' => $containerType
                ]);

                $arena[$bay][$row][$col] = $container->id;
                $placedCounts[$destinationPort]++;
            } catch (\Exception $e) {
                Log::error("Failed to create container: " . $e->getMessage());
                continue;
            }
        }

        // Log the counts we achieved
        foreach ($placedCounts as $port => $count) {
            Log::info("Port $port: Placed $count containers (target: $containersPerPort)");
        }

        return $arena;
    }

    private function createContainer($bay, $row, $col, $bayTypes, $userPort, $validPorts, $nextId, $bottomRowIndex)
    {
        $bayType = $bayTypes[$bay] ?? 'dry';
        $containerType = $bayType === 'reefer' ? 'reefer' : (rand(1, 10) <= 8 ? 'dry' : 'reefer');

        if ($containerType === 'reefer' && $bayType !== 'reefer') {
            return null;
        }

        $availablePorts = array_values(array_diff($validPorts, [$userPort]));
        if (empty($availablePorts)) {
            return null;
        }

        // Untuk container di baris bawah, 30% chance untuk jadi tujuan user
        $isForUserPort = ($row === $bottomRowIndex) && (rand(1, 10) <= 3);
        if ($isForUserPort) {
            $originPort = $availablePorts[array_rand($availablePorts)];
            $destinationPort = $userPort;
        } else {
            $isForUnloading = (rand(1, 10) <= 6);
            if ($isForUnloading) {
                $originPort = $availablePorts[array_rand($availablePorts)];
                $destinationPort = $availablePorts[array_rand($availablePorts)];
                while ($destinationPort == $originPort && count($availablePorts) > 1) {
                    $destinationPort = $availablePorts[array_rand($availablePorts)];
                }
            } else {
                $originPort = $userPort;
                $destinationPort = $availablePorts[array_rand($availablePorts)];
            }
        }

        $cardId = (string)$nextId;
        $revenue = rand(5000000, 15000000);
        $priority = rand(1, 10) <= 7 ? "Committed" : "Non-Committed";

        try {
            $card = Card::create([
                'id' => $cardId,
                'type' => $containerType,
                'priority' => $priority,
                'origin' => $originPort,
                'destination' => $destinationPort,
                'quantity' => 1,
                'revenue' => $revenue
            ]);

            $container = Container::create([
                'color' => $this->generateContainerColor($destinationPort),
                'card_id' => $cardId,
                'type' => $containerType
            ]);

            return [
                'container_id' => $container->id,
                'card_id' => $card->id,
                'destination' => $destinationPort, // informasi tujuan ditambahkan
            ];
        } catch (\Exception $e) {
            Log::error("Failed to create container: " . $e->getMessage());
            return null;
        }
    }

    private function generateContainerColor($destination)
    {
        $colorMap = [
            'SBY' => 'red',
            'MKS' => 'blue',
            'MDN' => 'green',
            'JYP' => 'yellow',
            'BPN' => 'purple',
            'BKS' => 'orange',
            'BGR' => 'pink',
            'BTH' => 'brown',
            'AMQ' => 'cyan',
            'SMR' => 'teal',
        ];

        return $colorMap[$destination] ?? 'gray';
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
        return CardTemporary::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->with('card') // This will eager load the card relationship
            ->get();
    }

    public function acceptCardTemporary(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'card_temporary_id' => 'required|exists:cards,id',
            'round' => 'sometimes|integer|min:1',
        ]);
        $cardTemporary = CardTemporary::where('card_id', $validated['card_temporary_id'])
            ->where('room_id', $validated['room_id'])
            ->first();
        $cardTemporary->status = 'accepted';

        if (isset($validatedData['round'])) {
            $cardTemporary->round = $validatedData['round'];
        }

        $cardTemporary->save();

        return response()->json(['message' => 'Sales call card accepted.']);
    }

    public function rejectCardTemporary(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'card_temporary_id' => 'required|exists:cards,id',
            'round' => 'sometimes|integer|min:1',
        ]);
        $cardTemporary = CardTemporary::where('card_id', $validated['card_temporary_id'])
            ->where('room_id', $validated['room_id'])
            ->first();
        $cardTemporary->status = 'rejected';

        if (isset($validatedData['round'])) {
            $cardTemporary->round = $validatedData['round'];
        }

        $cardTemporary->save();

        return response()->json(['message' => 'Sales call card rejected.']);
    }

    public function getUsersRanking($roomId)
    {
        $room = Room::findOrFail($roomId);
        $userIds = json_decode($room->users, true);

        $shipBays = ShipBay::with('user:id,name')
            ->whereIn('user_id', $userIds)
            ->where('room_id', $roomId)
            ->distinct('user_id')
            ->get()
            ->map(function ($shipBay) {
                $shipBay->total_revenue = $shipBay->revenue - $shipBay->penalty;
                return $shipBay;
            });

        $ranking = $shipBays->sortByDesc('total_revenue')->values();

        return response()->json($ranking);
    }

    public function getAvailableUsers()
    {
        $users = User::where('is_admin', false)
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        return response()->json($users);
    }

    public function updateSwapConfig(Request $request, Room $room)
    {
        $request->validate([
            'swap_config' => 'required|array',
        ]);

        $room->swap_config = json_encode($request->swap_config);
        $room->save();

        return response()->json([
            'message' => 'Swap configuration updated successfully',
            'swap_config' => json_decode($room->swap_config),
        ]);
    }
}
