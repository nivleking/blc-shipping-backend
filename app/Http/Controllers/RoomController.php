<?php

namespace App\Http\Controllers;

use App\Models\CapacityUptake;
use App\Models\Card;
use App\Models\CardTemporary;
use App\Models\Container;
use App\Models\Room;
use App\Models\Deck;
use App\Models\MarketIntelligence;
use App\Models\ShipBay;
use App\Models\ShipDock;
use App\Models\ShipLayout;
use App\Models\User;
use App\Utilities\UtilitiesHelper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomController extends Controller
{
    use UtilitiesHelper;

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
            'backlog_penalty_per_container_cost' => 'required|integer|min:1',
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
                'backlog_penalty_per_container_cost' => $validated['backlog_penalty_per_container_cost'],
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
                    CapacityUptake::updateOrCreate(
                        [
                            'user_id' => $bay->user_id,
                            'room_id' => $room->id,
                            'week' => $bay->current_round,
                            'port' => $bay->port
                        ],
                        [
                            'arena_end' => $bay->arena
                        ]
                    );

                    // Existing code for bay_statistics_history
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
            'cards_must_process_per_round' => 'integer|min:1',
            'move_cost' => 'integer|min:1',
            'extra_moves_cost' => 'integer|min:1',
            'ideal_crane_split' => 'integer|min:1',
            'backlog_penalty_per_container_cost' => 'integer|min:1',
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
        if (isset($validated['backlog_penalty_per_container_cost'])) {
            $room->backlog_penalty_per_container_cost = $validated['backlog_penalty_per_container_cost'];
        }

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

    private function calculateRestowagePenalties($room, $userId)
    {
        $shipBay = ShipBay::where('room_id', $room->id)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return ['penalty' => 0, 'moves' => 0, 'containers' => []];
        }

        // Get the port sequence from swap configuration
        $swapConfig = json_decode($room->swap_config, true);
        if (empty($swapConfig)) {
            return ['penalty' => 0, 'moves' => 0, 'containers' => []];
        }

        // Get the current port
        $currentPort = $shipBay->port;

        // Build the port sequence starting from current port
        $portSequence = [];
        $nextPort = $currentPort;
        $visited = [$currentPort => true];

        while (isset($swapConfig[$nextPort]) && !isset($visited[$swapConfig[$nextPort]])) {
            $nextPort = $swapConfig[$nextPort];
            $portSequence[] = $nextPort;
            $visited[$nextPort] = true;
        }

        // Get all containers in the bay
        $arena = is_array($shipBay->arena) ? $shipBay->arena : json_decode($shipBay->arena, true);
        if (!isset($arena['containers']) || empty($arena['containers'])) {
            return ['penalty' => 0, 'moves' => 0, 'containers' => []];
        }

        // Group containers by stack
        $stacks = [];
        foreach ($arena['containers'] as $container) {
            if (!isset($container['bay']) || !isset($container['row']) || !isset($container['col']) || !isset($container['id'])) {
                continue;
            }

            $stackKey = $container['bay'] . '-' . $container['col'];
            if (!isset($stacks[$stackKey])) {
                $stacks[$stackKey] = [];
            }

            $containerObj = Container::where('id', $container['id'])
                ->where('deck_id', $room->deck_id)
                ->first();
            if ($containerObj && $containerObj->card) {
                $destination = $containerObj->card->destination;

                $stacks[$stackKey][] = [
                    'id' => $container['id'],
                    'row' => $container['row'],
                    'destination' => $destination,
                    'position' => $container['bay'] * 1000 + $container['row'] * 100 + $container['col']
                ];
            }
        }

        // Calculate port priority - lower number means earlier port visit
        $portPriority = [];
        foreach ($portSequence as $index => $port) {
            $portPriority[$port] = $index;
        }

        $restowageContainers = [];
        $moveDetails = [];

        // Track unique containers that need to be moved at each port
        $containersToMoveByStack = [];

        foreach ($stacks as $stackKey => $containers) {
            // Sort containers by row (top to bottom)
            usort($containers, function ($a, $b) {
                return $a['row'] - $b['row'];
            });

            // Initialize container tracking for this stack
            $containersToMoveByStack[$stackKey] = [];

            // Check each container from bottom to top for stacking violations
            for ($j = count($containers) - 1; $j >= 0; $j--) {
                $targetContainer = $containers[$j];
                $targetDestination = $targetContainer['destination'];

                // Skip if destination is unknown or current port
                if (!$targetDestination || $targetDestination === $currentPort) {
                    continue;
                }

                // Skip if destination is not in our port sequence
                if (!isset($portPriority[$targetDestination])) {
                    continue;
                }

                // Check for containers above that need to be moved
                $blockingContainers = [];

                for ($i = 0; $i < $j; $i++) {
                    $topContainer = $containers[$i];
                    $topDestination = $topContainer['destination'];

                    if (!$topDestination || $topDestination === $currentPort) {
                        continue;
                    }

                    if (!isset($portPriority[$topDestination])) {
                        continue;
                    }

                    // If top container's port visit is LATER than target container's
                    if ($portPriority[$targetDestination] < $portPriority[$topDestination]) {
                        $blockingContainers[] = $topContainer;

                        // Track this container as needing to be moved
                        $containersToMoveByStack[$stackKey][$topContainer['id']] = $topContainer;
                    }
                }

                if (!empty($blockingContainers)) {
                    $isNextPort = ($targetDestination === $portSequence[0]);

                    // Store the violation details
                    $moveDetails[] = [
                        'stack' => $stackKey,
                        'blockedContainer' => $targetContainer,
                        'blockedDestination' => $targetDestination,
                        'isNextPort' => $isNextPort,
                        'blockingContainers' => $blockingContainers,
                        'blockingDestinations' => array_map(function ($c) {
                            return $c['destination'];
                        }, $blockingContainers)
                    ];

                    // Create entries for each blocking container
                    foreach ($blockingContainers as $blockingContainer) {
                        $restowageContainers[] = [
                            'container_id' => $targetContainer['id'],
                            'blocking_container_id' => $blockingContainer['id'],
                            'destination' => $targetDestination,
                            'blocking_destination' => $blockingContainer['destination'],
                            'is_next_port' => $isNextPort,
                            'stack' => $stackKey
                        ];
                    }
                }
            }
        }

        // Calculate total unique containers that need to be moved
        $totalContainersToMove = 0;
        foreach ($containersToMoveByStack as $stackContainers) {
            $totalContainersToMove += count($stackContainers);
        }

        // Calculate total moves (each container = 2 moves)
        $restowageMoves = $totalContainersToMove * 2;

        // Calculate penalty based on move cost
        $moveCost = $room->extra_moves_cost ?? $room->move_cost ?? 50000;
        $restowagePenalty = $restowageMoves * $moveCost;

        // Add moves_required to each container entry
        foreach ($restowageContainers as &$container) {
            $container['moves_required'] = 2; // Each container requires 2 moves
        }

        return [
            'penalty' => $restowagePenalty,
            'moves' => $restowageMoves,
            'containers' => $restowageContainers,
            'move_details' => $moveDetails,
            'unique_containers_to_move' => $totalContainersToMove
        ];
    }

    // New endpoint to get stacking order
    public function getProperStackingOrder($roomId, $port)
    {
        $room = Room::find($roomId);
        if (!$room) {
            return response()->json(['error' => 'Room not found'], 404);
        }

        $swapConfig = json_decode($room->swap_config, true);
        if (empty($swapConfig)) {
            return response()->json(['error' => 'Swap configuration not found'], 404);
        }

        // Build the port sequence starting from current port
        $portSequence = [];
        $nextPort = $port;
        $visited = [$port => true];

        // Prevent infinite loops due to circular references
        while (isset($swapConfig[$nextPort]) && !isset($visited[$swapConfig[$nextPort]])) {
            $nextPort = $swapConfig[$nextPort];
            $portSequence[] = $nextPort;
            $visited[$nextPort] = true;
        }

        return response()->json([
            'current_port' => $port,
            'recommended_stacking_order' => ($portSequence) // Bottom to top
        ]);
    }

    public function getRestowageStatus(Request $request, $roomId)
    {
        $userId = $request->user()->id;
        $room = Room::find($roomId);

        if (!$room) {
            return response()->json(['error' => 'Room not found'], 404);
        }

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json([
                'restowage_containers' => [],
                'restowage_moves' => 0,
                'restowage_penalty' => 0
            ]);
        }

        // On-demand calculation for current status
        $restowageDetails = $this->calculateRestowagePenalties($room, $userId);

        return response()->json([
            'restowage_containers' => $restowageDetails['containers'],
            'restowage_moves' => $restowageDetails['moves'],
            'restowage_penalty' => $restowageDetails['penalty'],
            'move_details' => $restowageDetails['move_details']
        ]);
    }

    public function swapBays(Request $request, Room $room)
    {
        $userIds = json_decode($room->users, true);

        $swapConfig = json_decode($room->swap_config, true);

        // Create port-to-user mapping for easier lookup
        $portToUserMap = [];
        foreach ($userIds as $userId) {
            $shipBay = ShipBay::where('room_id', $room->id)
                ->where('user_id', $userId)
                ->first();
            if ($shipBay) {
                $portToUserMap[$shipBay->port] = $userId;
            }
        }

        if (count($userIds) < 2) {
            return response()->json(['message' => 'Not enough users to swap bays'], 400);
        }

        $shipBays = ShipBay::whereIn('user_id', $userIds)
            ->where('room_id', $room->id)
            ->get();

        $movedContainers = [];
        // Calculate backlog penalties containers / not loaded for each user
        foreach ($userIds as $userId) {
            $shipBay = ShipBay::where('room_id', $room->id)
                ->where('user_id', $userId)
                ->first();

            $shipDock = ShipDock::where('room_id', $room->id)
                ->where('user_id', $userId)
                ->first();

            CapacityUptake::updateOrCreate(
                [
                    'user_id' => $userId,
                    'room_id' => $room->id,
                    'week' => $shipBay->current_round,
                    'port' => $shipBay->port
                ],
                [
                    'arena_end' => $shipBay->arena
                ]
            );

            // Calculate backlog penalties for containers sitting in dock
            $backlogDetails = $this->calculateBacklogPenalty($room, $userId, $shipBay->current_round + 1);

            // Calculate restowage penalties
            $restowageDetails = $this->calculateRestowagePenalties($room, $userId);

            // Add or update backlog penalty
            if (!isset($shipBay->backlog_penalty)) {
                $shipBay->backlog_penalty = 0;
            }

            $shipBay->backlog_penalty += $backlogDetails['penalty'];
            $shipBay->backlog_containers = $backlogDetails['backlog_containers'];

            // Add or update restowage penalty
            if (!isset($shipBay->restowage_penalty)) {
                $shipBay->restowage_penalty = 0;
            }

            $shipBay->restowage_penalty += $restowageDetails['penalty'];
            $shipBay->restowage_moves += $restowageDetails['moves'];
            $shipBay->restowage_containers = $restowageDetails['containers'];

            // Update total penalty
            $shipBay->penalty = ($shipBay->backlog_penalty ?? 0) + ($shipBay->restowage_penalty ?? 0);

            // Update total revenue calculation (subtract penalties)
            $shipBay->total_revenue = ($shipBay->revenue ?? 0) - ($shipBay->penalty ?? 0);

            // Get restow containers that need to be moved
            $restowageContainers = $restowageDetails['containers'];
            if (!empty($restowageContainers)) {
                $bayArena = is_array($shipBay->arena) ? $shipBay->arena : json_decode($shipBay->arena, true);

                // Get current port and determine next port from swap config
                $currentPort = $shipBay->port;
                $nextPort = $swapConfig[$currentPort] ?? null;

                if (!$nextPort) continue;

                // Get the user ID of the next port
                $nextUserId = $portToUserMap[$nextPort] ?? null;
                if (!$nextUserId) continue;

                // Get or create dock for NEXT port's user
                $nextUserDock = ShipDock::firstOrNew([
                    'room_id' => $room->id,
                    'user_id' => $nextUserId
                ]);

                if (!$nextUserDock->exists) {
                    $nextUserDock->port = $nextPort;
                    $nextUserDock->dock_size = json_encode($room->bay_size);
                    $nextUserDock->arena = json_encode(['containers' => [], 'totalContainers' => 0]);
                }

                // Process dock arena of next user
                $dockArena = is_array($nextUserDock->arena)
                    ? $nextUserDock->arena
                    : json_decode($nextUserDock->arena, true);

                if (!isset($dockArena['containers'])) {
                    $dockArena = ['containers' => [], 'totalContainers' => 0];
                }

                // Only identify blocking containers to be moved
                $blockingContainerIds = [];
                foreach ($restowageContainers as $container) {
                    if (isset($container['blocking_container_id'])) {
                        $blockingContainerIds[] = $container['blocking_container_id'];
                    }
                }

                $blockingContainerIds = array_unique($blockingContainerIds);

                // Move containers from current user's bay to next user's dock
                $containersToMove = [];
                $newBayContainers = [];

                foreach ($bayArena['containers'] as $container) {
                    if (in_array($container['id'], $blockingContainerIds)) {
                        $container['is_restowed'] = true;
                        $containersToMove[] = $container;
                    } else {
                        $newBayContainers[] = $container;
                    }
                }

                // Update current user's bay
                $bayArena['containers'] = $newBayContainers;
                $bayArena['totalContainers'] = count($newBayContainers);

                $bayArena = $this->applyGravityToStacks($bayArena);
                $shipBay->arena = json_encode($bayArena);

                // Add containers to next user's dock
                $nextPosition = 0;
                if (!empty($dockArena['containers'])) {
                    foreach ($dockArena['containers'] as $dockContainer) {
                        if (isset($dockContainer['position']) && $dockContainer['position'] > $nextPosition) {
                            $nextPosition = $dockContainer['position'];
                        }
                    }
                    $nextPosition += 1;
                }

                foreach ($containersToMove as $container) {
                    $container['position'] = $nextPosition++;
                    $container['area'] = 'docks-' . $container['position'];
                    $dockArena['containers'][] = $container;

                    $movedContainers[] = [
                        'container_id' => $container['id'],
                        'from_user_id' => $userId,
                        'from_port' => $currentPort,
                        'to_user_id' => $nextUserId,
                        'to_port' => $nextPort,
                        'reason' => 'blocking_container'
                    ];
                }

                $dockArena['totalContainers'] = count($dockArena['containers']);
                $nextUserDock->arena = json_encode($dockArena);
                $nextUserDock->save();
            }
            $shipBay->save();
        }

        // Get the swap configuration
        $swapConfig = json_decode($room->swap_config, true);

        if (empty($swapConfig)) {
            return response()->json(['message' => 'No swap configuration found'], 400);
        }

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
                'restowage_moves' => $bay->restowage_moves ?? 0,
                'restowage_penalty' => $bay->restowage_penalty ?? 0,
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
            $bay->restowage_moves = 0;
            $bay->restowage_containers = null;
            $bay->save();
        }

        // Now reload the bays to get the updated arenas (with restowage containers removed)
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

        // Perform the swaps according to the configuration
        foreach ($swapConfig as $fromPort => $toPort) {
            if (isset($baysByPort[$fromPort]) && isset($originalArenas[$toPort])) {
                $sourceBay = $baysByPort[$toPort];
                $sourceBay->arena = $originalArenas[$fromPort];
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

        // After increment round, get the new round
        $newRound = ShipBay::where('room_id', $room->id)->first()->current_round;

        // Get the cards_limit_per_round setting
        $cardsLimitPerRound = $room->cards_limit_per_round;

        // For each user, assign new cards for the next round
        foreach ($userIds as $userId) {
            // Find unassigned cards for this user (where round is NULL)
            $unassignedCardIds = CardTemporary::where([
                'user_id' => $userId,
                'room_id' => $room->id,
                'status' => 'selected',
            ])
                ->whereNull('round')
                ->orderByRaw('CAST(card_id AS UNSIGNED) ASC')
                ->limit($cardsLimitPerRound)
                ->pluck('id')
                ->toArray();

            // Bulk update the round field for the selected cards
            if (!empty($unassignedCardIds)) {
                CardTemporary::whereIn('id', $unassignedCardIds)
                    ->update(['round' => $newRound]);
            }
        }

        // Process unhandled sales calls / unhandled committed cards
        $unhandledCards = [];
        foreach ($userIds as $userId) {
            // Find committed cards from previous round that weren't processed
            $userUnhandledCards = CardTemporary::join('cards', function ($join) {
                $join->on('cards.id', '=', 'card_temporaries.card_id')
                    ->on('cards.deck_id', '=', 'card_temporaries.deck_id');
            })
                ->where([
                    'card_temporaries.user_id' => $userId,
                    'card_temporaries.room_id' => $room->id,
                    'card_temporaries.status' => 'selected',
                ])
                ->where('card_temporaries.round', '<', $newRound)
                ->where('cards.priority', 'Committed')
                ->select('card_temporaries.*')
                ->get();

            // Mark these cards as backlog and keep them in the current round
            foreach ($userUnhandledCards as $card) {
                $card->is_backlog = true;
                $card->original_round = $card->round;
                $card->round = $newRound; // Move to current round
                $card->save();
            }

            $unhandledCards = array_merge($unhandledCards, $userUnhandledCards->toArray());
        }

        foreach ($userIds as $userId) {
            $shipBay = ShipBay::where('room_id', $room->id)
                ->where('user_id', $userId)
                ->first();

            // Create capacity uptake for new round
            CapacityUptake::updateOrCreate(
                [
                    'user_id' => $userId,
                    'room_id' => $room->id,
                    'week' => $newRound,
                    'port' => $shipBay->port
                ],
                [
                    'arena_start' => $shipBay->arena,
                    'arena_end' => null
                ]
            );
        }

        return response()->json([
            'message' => 'Bays swapped successfully',
            'movedRestowageContainers' => $movedContainers
        ]);
    }

    // Add this function after moveRestowageContainers
    private function applyGravityToStacks($arena)
    {
        if (!isset($arena['containers']) || empty($arena['containers'])) {
            return $arena;
        }

        // Group containers by bay-column (stack)
        $stacks = [];
        foreach ($arena['containers'] as $container) {
            if (!isset($container['bay']) || !isset($container['row']) || !isset($container['col'])) {
                continue;
            }

            $stackKey = $container['bay'] . '-' . $container['col'];
            if (!isset($stacks[$stackKey])) {
                $stacks[$stackKey] = [];
            }

            $stacks[$stackKey][] = $container;
        }

        // Apply gravity to each stack
        $updatedContainers = [];
        foreach ($stacks as $stackKey => $containers) {
            // Sort by row (top to bottom)
            usort($containers, function ($a, $b) {
                return $a['row'] - $b['row'];
            });

            // Count containers in this stack
            $containerCount = count($containers);

            // Get bay and column from stack key
            list($bay, $col) = explode('-', $stackKey);
            $bay = (int)$bay;
            $col = (int)$col;

            // Reposition containers from bottom up (no gaps)
            $maxRow = 0; // Track the highest row we've seen
            for ($i = 0; $i < $containerCount; $i++) {
                $container = $containers[$i];
                $maxRow = max($maxRow, $container['row']);
            }

            // Start from the bottom row and work upward
            $newRow = $maxRow;
            for ($i = $containerCount - 1; $i >= 0; $i--) {
                $container = $containers[$i];

                // Update row position
                $container['row'] = $newRow;
                $updatedContainers[] = $container;

                // Move up to next position
                $newRow--;
            }
        }

        // Update arena with repositioned containers
        $arena['containers'] = $updatedContainers;
        return $arena;
    }

    private function calculateBacklogPenalty($room, $userId, $currentRound)
    {
        // Get ship dock to find containers that weren't moved
        $shipDock = ShipDock::where('room_id', $room->id)
            ->where('user_id', $userId)
            ->first();

        if (!$shipDock) {
            return ['penalty' => 0, 'backlog_containers' => []];
        }

        // Parse arena data to get containers in dock
        $arenaData = json_decode($shipDock->arena, true);
        $dockContainers = isset($arenaData['containers']) ? $arenaData['containers'] : [];

        if (empty($dockContainers)) {
            return ['penalty' => 0, 'backlog_containers' => []];
        }

        // Build container ID list
        $containerIds = array_map(function ($container) {
            return $container['id'];
        }, $dockContainers);

        // Get all accepted card temporaries for previous rounds
        $cardTemporaries = CardTemporary::where('user_id', $userId)
            ->where('room_id', $room->id)
            ->where('status', 'accepted')
            ->where('round', '<', $currentRound) // Only previous rounds
            ->with(['card' => function ($query) {
                $query->select('id', 'deck_id'); // Get only necessary fields
            }])
            ->get();

        // Get containers from these card temporaries
        $acceptedCardIds = $cardTemporaries->pluck('card_id')->toArray();
        $acceptedDeckIds = $cardTemporaries->pluck('deck_id')->toArray();

        // Match containers with accepted cards
        $backlogContainers = [];
        $totalPenalty = 0;

        foreach ($containerIds as $containerId) {
            $container = Container::find($containerId);
            if (!$container) continue;

            // Check if this container belongs to an accepted card
            $cardTemp = CardTemporary::where('room_id', $room->id)
                ->where('user_id', $userId)
                ->where('card_id', $container->card_id)
                ->where('deck_id', $container->deck_id)
                ->where('status', 'accepted')
                ->where('round', '<', $currentRound) // Only previous rounds
                ->first();

            $marketIntelligence = MarketIntelligence::where('deck_id', $container->deck_id);

            if ($cardTemp) {
                // Calculate weeks the container has been sitting in dock
                $weeksPending = $currentRound - $cardTemp->round;

                if ($weeksPending > 0) {
                    $penalty = $weeksPending * $room->backlog_penalty_per_container_cost;
                    $totalPenalty += $penalty;

                    $backlogContainers[] = [
                        'container_id' => $containerId,
                        'card_id' => $container->card_id,
                        'round_accepted' => $cardTemp->round,
                        'weeks_pending' => $weeksPending,
                        'penalty' => $penalty
                    ];
                }
            }
        }

        return [
            'penalty' => $totalPenalty,
            'backlog_containers' => $backlogContainers
        ];
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

                CapacityUptake::updateOrCreate([
                    'user_id' => $userId,
                    'room_id' => $room->id,
                    'week' => 1,
                    'port' => $port
                ], [
                    'arena_start' => $shipBay->arena,
                    'arena_end' => null
                ]);

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

        $maxTotalContainersInShipbay = $bayCount * $rowCount * $colCount;
        $targetContainers = 0.3 * $maxTotalContainersInShipbay;

        // Get room information
        $room = Room::find(request()->route('room')->id);
        if (!$room) {
            return $flatArena;
        }

        $swapConfig = json_decode($room->swap_config, true) ?? [];

        // Remove user's own port from destination options
        $destinationPorts = array_values(array_filter($allPorts, function ($port) use ($userPort) {
            return $port !== $userPort;
        }));

        // Sort destination ports by proximity to userPort using the swap config
        $sortedDestinationPorts = $this->sortByProximity($userPort, $destinationPorts, $swapConfig);

        // Create a port sequence for checking valid stacking order
        $portSequence = array_merge([$userPort], $sortedDestinationPorts);

        // Build a map of port priorities (higher number = later port)
        $portPriority = [];
        foreach ($portSequence as $index => $port) {
            $portPriority[$port] = $index;
        }

        // Get distribution percentages based on number of destination ports
        $distributionPercentages = $this->getDistributionPercentages(count($destinationPorts));

        // For each destination port, assign a distribution percentage based on proximity
        $distributionMap = [];
        foreach ($sortedDestinationPorts as $index => $port) {
            if (isset($distributionPercentages[$index])) {
                $distributionMap[$port] = $distributionPercentages[$index];
            }
        }

        // Calculate actual container counts based on percentages
        $containersPerDestination = [];
        $total = array_sum($distributionMap);

        foreach ($distributionMap as $port => $percentage) {
            $containersPerDestination[$port] = round($targetContainers * ($percentage / $total));
        }

        // Ensure we match exactly our target container count
        $totalPlanned = array_sum($containersPerDestination);
        if ($totalPlanned !== $targetContainers) {
            // Find highest percentage port to adjust
            arsort($distributionMap);
            $highestPort = array_key_first($distributionMap);
            $containersPerDestination[$highestPort] += ($targetContainers - $totalPlanned);
        }

        // Prepare container ID counter
        $roomPrefix = "R" . $room->id . "-";
        $counter = 1;
        $nextId = $roomPrefix . $counter;
        while (Card::where('id', (string)$nextId)->exists()) {
            $counter++;
            $nextId = $roomPrefix . $counter;
        }

        // Define all possible cell positions
        $allPositions = [];
        for ($bay = 0; $bay < $bayCount; $bay++) {
            for ($row = 0; $row < $rowCount; $row++) {
                for ($col = 0; $col < $colCount; $col++) {
                    $allPositions[] = [
                        'bay' => $bay,
                        'row' => $row,
                        'col' => $col
                    ];
                }
            }
        }

        // Sort positions: bottom rows first, then upper rows
        usort($allPositions, function ($a, $b) use ($bottomRowIndex) {
            // Compare rows (bottom row first)
            if ($a['row'] != $b['row']) {
                return $a['row'] > $b['row'] ? -1 : 1;
            }
            // Same row, sort by bay
            if ($a['bay'] != $b['bay']) {
                return $a['bay'] - $b['bay'];
            }
            // Same bay, sort by column
            return $a['col'] - $b['col'];
        });

        // Track occupied positions and their content
        $occupiedPositions = [];
        $containerPositions = []; // Will store destination port for each position

        // Sort destination ports by sequence (farthest ports first - for bottom placement)
        $destinationsByPriority = $destinationPorts;
        // Sort destinations - farther ports (lower priority) should be placed first/bottom
        usort($destinationsByPriority, function ($a, $b) use ($portPriority) {
            return $portPriority[$b] <=> $portPriority[$a];
        });

        // Create containers according to our distribution - process furthest ports first
        foreach ($destinationsByPriority as $destinationPort) {
            $count = $containersPerDestination[$destinationPort];

            for ($i = 0; $i < $count; $i++) {
                // Find a valid position
                $position = null;

                foreach ($allPositions as $pos) {
                    $bay = $pos['bay'];
                    $row = $pos['row'];
                    $col = $pos['col'];

                    // Position key for tracking
                    $positionKey = "$bay-$row-$col";

                    // Skip if already occupied
                    if (isset($occupiedPositions[$positionKey])) {
                        continue;
                    }

                    // Check if position is valid - either bottom row or has container below
                    $isBottomRow = ($row === $bottomRowIndex);
                    $belowPositionKey = "$bay-" . ($row + 1) . "-$col";
                    $hasContainerBelow = isset($occupiedPositions[$belowPositionKey]);

                    // If there's a container below, check port sequence to avoid restowage
                    if ($hasContainerBelow) {
                        $belowDestination = $containerPositions[$belowPositionKey];

                        // We want containers for later ports to be below containers for earlier ports
                        // If below container is for an EARLIER port (higher priority) than current,
                        // this would create a restowage issue - skip this position
                        if ($portPriority[$belowDestination] < $portPriority[$destinationPort]) {
                            continue; // This would cause a restow - skip this position
                        }
                    }

                    // Position is valid - either bottom row or proper stacking order
                    if ($isBottomRow || $hasContainerBelow) {
                        $position = $pos;
                        break;
                    }
                }

                // If no valid position found, skip this container
                if (!$position) {
                    continue;
                }

                $bay = $position['bay'];
                $row = $position['row'];
                $col = $position['col'];
                $positionKey = "$bay-$row-$col";

                // Mark position as occupied and store destination
                $occupiedPositions[$positionKey] = true;
                $containerPositions[$positionKey] = $destinationPort;

                // Determine container type based on bay type
                $bayType = $bayTypes[$bay] ?? 'dry';
                $containerType = 'dry'; // Default to dry

                if ($bayType === 'reefer') {
                    $containerType = 'reefer';
                }

                // Calculate flat position index for storage format
                $flatPositionIndex = $bay * $rowCount * $colCount + $row * $colCount + $col;

                // Calculate revenue based on origin-destination pair
                $revenue = $this->calculateRevenueByDistance($userPort, $destinationPort, $containerType);

                // Create card with random priority
                $priority = rand(1, 10) <= 5 ? "Committed" : "Non-Committed";

                // Generate a unique card ID
                $cardId = $roomPrefix . $counter;
                while (Card::where('id', (string)$cardId)->exists()) {
                    $counter++;
                    $cardId = $roomPrefix . $counter;
                }

                try {
                    // Create card record
                    $card = Card::create([
                        'id' => $cardId,
                        'deck_id' => $room->deck_id,
                        'type' => $containerType,
                        'priority' => $priority,
                        'origin' => $userPort,
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
                        'deck_id' => $room->deck_id,
                        'type' => $containerType
                    ]);

                    // Add to flat arena structure
                    $flatArena['containers'][] = [
                        'id' => $container->id,
                        'position' => $flatPositionIndex,
                        'bay' => $bay,
                        'row' => $row,
                        'col' => $col,
                        'cardId' => $cardId,
                        'type' => $containerType,
                        'origin' => $userPort,
                        'destination' => $destinationPort
                    ];

                    $flatArena['totalContainers']++;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $flatArena;
    }

    private function calculateRevenueByDistance($origin, $destination, $containerType)
    {
        $originCode = $origin;
        $destinationCode = $destination;

        $basePrices = $this->getBasePriceMap();
        $key = "{$originCode}-{$destinationCode}-{$containerType}";

        if (isset($basePrices[$key])) {
            return $basePrices[$key];
        }

        return $containerType === 'reefer' ? 30000000 : 18000000;
    }

    private function sortByProximity($originPort, $destinationPorts, $swapConfig)
    {
        if (empty($swapConfig)) {
            return $destinationPorts;
        }

        $route = [];
        $currentPort = $originPort;
        $maxPorts = count($destinationPorts) + 1;
        $count = 0;

        // Build route starting from origin
        while ($currentPort && isset($swapConfig[$currentPort]) && $count < $maxPorts) {
            $route[] = $currentPort;
            $currentPort = $swapConfig[$currentPort];
            $count++;
        }

        // Add the last port if it's not already in the route
        if ($currentPort && !in_array($currentPort, $route)) {
            $route[] = $currentPort;
        }

        // If route is incomplete, add any missing ports
        foreach ($destinationPorts as $port) {
            if (!in_array($port, $route)) {
                $route[] = $port;
            }
        }

        // Remove origin port from route
        $route = array_values(array_filter($route, function ($port) use ($originPort) {
            return $port !== $originPort;
        }));

        // Order destination ports by proximity (first in route = closest)
        $sortedPorts = [];
        foreach ($route as $port) {
            if (in_array($port, $destinationPorts)) {
                $sortedPorts[] = $port;
            }
        }

        return $sortedPorts;
    }

    private function getDistributionPercentages($portCount)
    {
        switch ($portCount) {
            case 1:
                return [100]; // 100% to the only other port

            case 2:
                return [60, 40]; // 60% to closest, 40% to furthest

            case 3:
                return [50, 30, 20]; // 50% to closest, 30% to second, 20% to furthest

            case 4:
                return [36, 24, 21, 18]; // As specified in requirements (SBY example)

            case 5:
                return [30, 25, 20, 15, 10]; // Diminishing distribution

            case 6:
                return [25, 22, 18, 15, 12, 8]; // Diminishing distribution

            case 7:
                return [22, 19, 16, 13, 12, 10, 8]; // Diminishing distribution

            case 8:
                return [20, 17, 14, 12, 11, 10, 9, 7]; // Diminishing distribution

            case 9:
                return [18, 15, 13, 12, 11, 10, 8, 7, 6]; // Diminishing distribution

            default: // 10 or more ports
                return [16, 14, 12, 11, 10, 9, 8, 8, 7, 5]; // Diminishing distribution
        }
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
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        $currentRound = $shipBay->current_round;

        // Use a direct join instead of relationship loading
        $cardTemporaries = CardTemporary::select(
            'card_temporaries.*',
            'cards.type',
            'cards.priority',
            'cards.origin',
            'cards.destination',
            'cards.quantity',
            'cards.revenue'
        )
            ->join('cards', function ($join) {
                $join->on('cards.id', '=', 'card_temporaries.card_id')
                    ->on('cards.deck_id', '=', 'card_temporaries.deck_id');
            })
            ->where([
                'card_temporaries.room_id' => $roomId,
                'card_temporaries.user_id' => $userId,
                'card_temporaries.status' => 'selected',
            ])
            ->where(function ($query) use ($currentRound) {
                $query->where('card_temporaries.round', $currentRound)
                    ->orWhere('card_temporaries.is_backlog', true);
            })
            ->orderByRaw('card_temporaries.is_backlog DESC')
            ->orderByRaw("CASE WHEN cards.priority = 'Committed' THEN 1 ELSE 2 END")
            // ->orderByRaw("CAST(card_temporaries.card_id AS UNSIGNED) ASC")
            ->get();

        // Format the result to match what the frontend expects
        $formattedResult = $cardTemporaries->map(function ($temp) {
            // Create a card property with the joined data
            $temp->card = [
                'id' => $temp->card_id,
                'deck_id' => $temp->deck_id,
                'type' => $temp->type,
                'priority' => $temp->priority,
                'origin' => $temp->origin,
                'destination' => $temp->destination,
                'quantity' => $temp->quantity,
                'revenue' => $temp->revenue
            ];

            // Remove duplicated attributes
            unset($temp->type);
            unset($temp->priority);
            unset($temp->origin);
            unset($temp->destination);
            unset($temp->quantity);
            unset($temp->revenue);

            return $temp;
        });

        $room = Room::find($roomId);

        // Get first card limit cards from formattedResult
        $cardsLimit = $room->cards_limit_per_round;
        if ($formattedResult->count() > $cardsLimit) {
            $formattedResult = $formattedResult->take($cardsLimit);
        }

        return response()->json([
            "cards" => $formattedResult,
        ]);
    }

    public function acceptCardTemporary(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'card_temporary_id' => 'required|exists:cards,id',
            'round' => 'required|integer|min:1',
        ]);

        $cardTemporary = CardTemporary::where('card_id', $validated['card_temporary_id'])
            ->where('room_id', $validated['room_id'])
            ->first();

        $cardTemporary->status = 'accepted';
        $cardTemporary->round = $validated['round'];
        $cardTemporary->save();

        return response()->json(['message' => 'Sales call card accepted.']);
    }

    public function rejectCardTemporary(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'card_temporary_id' => 'required|exists:cards,id',
            'round' => 'required|integer|min:1',
        ]);

        $cardTemporary = CardTemporary::where('card_id', $validated['card_temporary_id'])
            ->where('room_id', $validated['room_id'])
            ->first();

        $cardTemporary->status = 'rejected';
        $cardTemporary->round = $validated['round'];
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
                    'backlog_penalty' => $shipBay->backlog_penalty,
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

    // public function getAvailableUsers()
    // {
    //     $users = User::where('is_admin', false)
    //         ->where('status', 'active')
    //         ->select('id', 'name')
    //         ->get();

    //     return response()->json($users);
    // }

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
