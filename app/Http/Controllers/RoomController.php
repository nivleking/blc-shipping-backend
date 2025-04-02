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
        $rooms = Room::with(['admin', 'deck', 'shipLayout'])->get();

        $admins = User::where('is_admin', true)->get()->keyBy('id');

        $decks = Deck::all();
        $layouts = ShipLayout::all();

        $availableUsers = User::where('is_admin', false)
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        return response()->json([
            'rooms' => $rooms,
            'admins' => $admins,
            'decks' => $decks,
            'layouts' => $layouts,
            'availableUsers' => $availableUsers
        ]);
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
            'move_cost' => 'required|integer|min:1',
            'extra_moves_cost' => 'required|integer|min:1',
            'ideal_crane_split' => 'required|integer|min:1',
            'cards_limit_per_round' => 'required|integer|min:1',
            'cards_must_process_per_round' => 'required|integer|min:1',
            'swap_config' => 'required|nullable|array'
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
                'move_cost' => $validated['move_cost'],
                'extra_moves_cost' => $validated['extra_moves_cost'],
                'ideal_crane_split' => $validated['ideal_crane_split'],
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
            if ($request->status === 'finished') {
                $userIds = json_decode($room->users, true) ?? [];

                $shipBays = ShipBay::whereIn('user_id', $userIds)
                    ->where('room_id', $room->id)
                    ->get();

                foreach ($shipBays as $bay) {
                    $existingRecord = DB::table('bay_statistics_history')
                        ->where('user_id', $bay->user_id)
                        ->where('room_id', $bay->room_id)
                        ->where('week', $bay->current_round)
                        ->first();

                    if (!$existingRecord) {
                        DB::table('bay_statistics_history')->insert([
                            'user_id' => $bay->user_id,
                            'room_id' => $bay->room_id,
                            'week' => $bay->current_round,
                            'discharge_moves' => $bay->discharge_moves,
                            'load_moves' => $bay->load_moves,
                            'bay_pairs' => $bay->bay_pairs,
                            'bay_moves' => $bay->bay_moves,
                            'long_crane_moves' => $bay->long_crane_moves,
                            'extra_moves_on_long_crane' => $bay->extra_moves_on_long_crane,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }

            $room->status = $request->status;
            $room->save();
            return response()->json($room);
        }

        $validated = $request->validate([
            'name' => 'string',
            'description' => 'string',
            'total_rounds' => 'integer|min:1',
            'cards_limit_per_round' => 'integer|min:1',
            'move_cost' => 'integer|min:1',
            'extra_moves_cost' => 'integer|min:1',
            'ideal_crane_split' => 'integer|min:1',
            'assigned_users' => 'array',
            'assigned_users.*' => 'exists:users,id',
            'deck' => 'exists:decks,id',
            'ship_layout' => 'exists:ship_layouts,id',
        ]);

        if (isset($validated['name'])) $room->name = $validated['name'];
        if (isset($validated['description'])) $room->description = $validated['description'];
        if (isset($validated['total_rounds'])) $room->total_rounds = $validated['total_rounds'];
        if (isset($validated['cards_limit_per_round'])) $room->cards_limit_per_round = $validated['cards_limit_per_round'];
        if (isset($validated['cards_must_process_per_round'])) $room->cards_must_process_per_round = $validated['cards_must_process_per_round'];
        if (isset($validated['move_cost'])) $room->move_cost = $validated['move_cost'];
        if (isset($validated['extra_moves_cost'])) $room->extra_moves_cost = $validated['extra_moves_cost'];
        if (isset($validated['ideal_crane_split'])) $room->ideal_crane_split = $validated['ideal_crane_split'];

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
        try {
            DB::beginTransaction();

            // 1. Find and delete card temporaries for this room
            CardTemporary::where('room_id', $room->id)->delete();

            // 2. Find all initial cards generated for this room
            $initialCards = Card::where('is_initial', true)
                ->where('generated_for_room_id', $room->id)
                ->get();

            // 3. Get container IDs associated with these cards
            $containerIds = [];
            foreach ($initialCards as $card) {
                $containers = $card->containers()->pluck('id')->toArray();
                $containerIds = array_merge($containerIds, $containers);
            }

            // 4. Delete the containers
            if (!empty($containerIds)) {
                Container::whereIn('id', $containerIds)->delete();
            }

            // 5. Delete the initial cards
            foreach ($initialCards as $card) {
                $card->delete();
            }

            // 6. Delete the room (and its related ship_bays via cascade)
            $room->delete();

            DB::commit();

            return response()->json([
                'message' => 'Room and all associated data deleted successfully'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function joinRoom(Request $request, Room $room)
    {
        $user = $request->user();
        $assignedUsers = json_decode($room->assigned_users, true) ?? [];

        if (!in_array($user->id, $assignedUsers)) {
            return response()->json([
                'message' => 'You are not assigned to this room'
            ], 403);
        }

        $users = json_decode($room->users, true) ?? [];

        // Check if user is already in the room
        if (in_array($user->id, $users)) {
            return response()->json([
                'room' => $room,
                'simulation_started' => ($room->status === 'active')
            ]);
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

        return response()->json([
            'room' => $room,
            'simulation_started' => ($room->status === 'active')
        ]);
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

        // Before incrementing current_round in swapBays method
        foreach ($shipBays as $bay) {
            // Store current statistics in history table
            DB::table('bay_statistics_history')->insert([
                'user_id' => $bay->user_id,
                'room_id' => $bay->room_id,
                'week' => $bay->current_round,
                'discharge_moves' => $bay->discharge_moves,
                'load_moves' => $bay->load_moves,
                'bay_pairs' => $bay->bay_pairs,
                'bay_moves' => $bay->bay_moves,
                'long_crane_moves' => $bay->long_crane_moves,
                'extra_moves_on_long_crane' => $bay->extra_moves_on_long_crane,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Reset statistics for the new week
            $bay->discharge_moves = 0;
            $bay->load_moves = 0;
            $bay->bay_pairs = json_encode([]);
            $bay->bay_moves = json_encode([]);
            $bay->long_crane_moves = 0;
            $bay->extra_moves_on_long_crane = 0;
            $bay->save();
        }

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

            // Perform the swaps
            foreach ($swapMap as $fromPort => $toPort) {
                if (isset($baysByPort[$fromPort]) && isset($originalArenas[$toPort])) {
                    $sourceBay = $baysByPort[$fromPort];
                    $sourceBay->arena = $originalArenas[$toPort];
                    $sourceBay->save();
                } else {
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

        $baySize = json_decode($room->bay_size, true);
        $bayCount = $room->bay_count;
        $bayTypes = json_decode($room->bay_types, true);

        foreach ($ports as $userId => $port) {
            $user = User::find($userId);

            if ($user) {
                $emptyArena = array_fill(
                    0,
                    $bayCount,
                    array_fill(
                        0,
                        $baySize['rows'],
                        array_fill(0, $baySize['columns'], null)
                    )
                );

                $flatArena = $this->generateInitialContainers($emptyArena, $port, $ports, $bayTypes);

                $shipBay = ShipBay::updateOrCreate(
                    ['user_id' => $user->id, 'room_id' => $room->id],
                    [
                        'port' => $port,
                        'arena' => json_encode($flatArena),
                        'revenue' => 0,
                        'section' => 'section2',
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

        // Initialize flat container format
        $flatArena = [
            'containers' => [],
            'totalContainers' => 0
        ];

        $containersPerPort = 5;

        // Get room and deck information
        $room = Room::find(request()->route('room')->id);
        if (!$room) {
            return $flatArena;
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

        // Create a list of ports excluding user's port
        $otherPorts = array_values(array_diff($validPorts, [$userPort]));

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

        // Shuffle bottom positions to randomize placement
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

            // Calculate position index for flat format
            $positionIndex = $bay * $rowCount * $colCount + $row * $colCount + $col;

            // Check if position is already occupied in our flat structure
            $positionOccupied = false;
            foreach ($flatArena['containers'] as $container) {
                if ($container['position'] === $positionIndex) {
                    $positionOccupied = true;
                    break;
                }
            }

            if ($positionOccupied) {
                continue;
            }

            // Determine container properties
            $destinationPort = $portToPlace;
            $isForUserPort = $destinationPort === $userPort;
            $originPort = $isForUserPort ? $otherPorts[array_rand($otherPorts)] : $userPort;

            // Determine container type based on bay type
            $bayType = $bayTypes[$bay] ?? 'dry';
            $containerType = $bayType === 'reefer' ? 'reefer' : 'dry';

            // Create the container
            $cardId = (string)$nextId++;
            $revenue = rand(5000000, 15000000);
            $priority = rand(1, 10) <= 5 ? "Committed" : "Non-Committed";

            try {
                // Create card record
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

                // Create container record
                $container = Container::create([
                    'color' => $this->generateContainerColor($destinationPort),
                    'card_id' => $cardId,
                    'type' => $containerType
                ]);

                // Add container to flat arena structure
                $flatArena['containers'][] = [
                    'id' => $container->id,
                    'position' => $positionIndex,
                    'bay' => $bay,
                    'row' => $row,
                    'col' => $col,
                    'cardId' => $cardId,
                    'type' => $containerType,
                    'origin' => $originPort,
                    'destination' => $destinationPort
                ];

                $flatArena['totalContainers']++;
                $placedCounts[$destinationPort]++;
            } catch (\Exception $e) {
                // Log error or handle exception
                continue;
            }
        }

        return $flatArena;
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
        try {
            $shipBays = ShipBay::where('room_id', $roomId)->get();
            $rankings = [];

            foreach ($shipBays as $shipBay) {
                $user = User::find($shipBay->user_id);
                if (!$user) continue;

                $rankings[] = [
                    'user_id' => $shipBay->user_id,
                    'user_name' => $user->name,
                    'port' => $shipBay->port,
                    'revenue' => $shipBay->revenue,
                    'penalty' => $shipBay->penalty,
                    'extra_moves_penalty' => $shipBay->extra_moves_penalty,
                    'total_revenue' => $shipBay->total_revenue,
                    'discharge_moves' => $shipBay->discharge_moves,
                    'load_moves' => $shipBay->load_moves,
                    'accepted_cards' => $shipBay->accepted_cards,
                    'rejected_cards' => $shipBay->rejected_cards,
                    'long_crane_moves' => $shipBay->long_crane_moves,
                    'extra_moves_on_long_crane' => $shipBay->extra_moves_on_long_crane
                ];
            }

            // Sort rankings by total_revenue in descending order
            usort($rankings, function ($a, $b) {
                return $b['total_revenue'] <=> $a['total_revenue'];
            });

            return response()->json($rankings);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve rankings'], 500);
        }
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
