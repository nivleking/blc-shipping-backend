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
use App\Models\SimulationLog;
use App\Models\User;
use App\Models\WeeklyPerformance;
use App\Utilities\RedisService;
use App\Utilities\UtilitiesHelper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RoomController extends Controller
{
    use UtilitiesHelper;

    protected $simulationLogController;
    protected $redisService;

    public function __construct(SimulationLogController $simulationLogController, RedisService $redisService)
    {
        $this->simulationLogController = $simulationLogController;
        $this->redisService = $redisService;
    }

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
            'restowage_cost' => 'required|integer|min:1',
            'cards_limit_per_round' => 'required|integer|min:1',
            'cards_must_process_per_round' => 'required|integer|min:1',
            'swap_config' => 'required|nullable|array',
            'dock_warehouse_costs.dry.committed' => 'required|integer|min:0',
            'dock_warehouse_costs.dry.non_committed' => 'required|integer|min:0',
            'dock_warehouse_costs.reefer.committed' => 'required|integer|min:0',
            'dock_warehouse_costs.reefer.non_committed' => 'required|integer|min:0',
            // 'extra_moves_cost' => 'required|integer|min:1',
            // 'ideal_crane_split' => 'required|integer|min:1',
        ]);

        $admin = $request->user();
        $layout = ShipLayout::findOrFail($validated['ship_layout']);

        $dockWarehouseCosts = [
            'default' => intval(($request->input('dock_warehouse_costs.dry.committed') +
                $request->input('dock_warehouse_costs.dry.non_committed') +
                $request->input('dock_warehouse_costs.reefer.committed') +
                $request->input('dock_warehouse_costs.reefer.non_committed')) / 4),
            'dry' => [
                'committed' => $request->input('dock_warehouse_costs.dry.committed'),
                'non_committed' => $request->input('dock_warehouse_costs.dry.non_committed')
            ],
            'reefer' => [
                'committed' => $request->input('dock_warehouse_costs.reefer.committed'),
                'non_committed' => $request->input('dock_warehouse_costs.reefer.non_committed')
            ]
        ];

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
                'restowage_cost' => $validated['restowage_cost'],
                'cards_limit_per_round' => $validated['cards_limit_per_round'],
                'cards_must_process_per_round' => $validated['cards_must_process_per_round'],
                'swap_config' => json_encode($validated['swap_config'] ?? []),
                'dock_warehouse_costs' => $dockWarehouseCosts,
                // 'extra_moves_cost' => $validated['extra_moves_cost'],
                // 'ideal_crane_split' => $validated['ideal_crane_split'],
            ]);

            return response()->json($room, 200);
        } catch (Exception $e) {
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

                    // Finalize the last week's performance
                    $this->finalizeWeeklyPerformance($room, $bay->user_id, $bay->current_round);

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
                            'bay_moves' => $bay->bay_moves,
                            'created_at' => now(),
                            'updated_at' => now()
                            // 'bay_pairs' => $bay->bay_pairs,
                            // 'long_crane_moves' => $bay->long_crane_moves,
                            // 'extra_moves_on_long_crane' => $bay->extra_moves_on_long_crane,
                        ]);
                    }

                    $shipDock = ShipDock::where('room_id', $room->id)
                        ->where('user_id', $bay->user_id)
                        ->first();

                    $logData = [
                        'user_id' => $bay->user_id,
                        'room_id' => $room->id,
                        'arena_bay' => $bay->arena,
                        'arena_dock' => $shipDock->arena,
                        'port' => $bay->port,
                        'section' => $bay->section,
                        'round' => $bay->current_round,
                        'revenue' => $bay->revenue ?? 0,
                        'penalty' => $bay->penalty ?? 0,
                        'total_revenue' => $bay->total_revenue,
                    ];

                    $this->simulationLogController->createLogEntry($logData);
                }
            } else if ($request->status === 'active') {
                // Create ship docks for all users in the room
                DB::beginTransaction();
                try {
                    // Get all users in the room
                    $userIds = json_decode($room->users, true) ?? [];

                    // Default dock layout and size
                    $dockLayout = [
                        'containers' => [],
                        'totalContainers' => 0
                    ];

                    $dockSize = [
                        'rows' => 8,
                        'columns' => 6
                    ];

                    // Create ship docks for each user
                    foreach ($userIds as $userId) {
                        // Get the user's port from the ship bay
                        $shipBay = ShipBay::where('room_id', $room->id)
                            ->where('user_id', $userId)
                            ->first();

                        if (!$shipBay) {
                            continue; // Skip if ship bay doesn't exist
                        }

                        // Create or update the ship dock
                        $shipDock = ShipDock::updateOrCreate(
                            ['user_id' => $userId, 'room_id' => $room->id],
                            [
                                'arena' => json_encode($dockLayout),
                                'dock_size' => json_encode($dockSize),
                                'port' => $shipBay->port
                            ]
                        );

                        $logData = [
                            'user_id' => $userId,
                            'room_id' => $room->id,
                            'arena_bay' => $shipBay->arena,
                            'arena_dock' => $shipDock->arena,
                            'port' => $shipBay->port,
                            'section' => $shipBay->section,
                            'round' => $shipBay->current_round,
                            'revenue' => $shipBay->revenue ?? 0,
                            'penalty' => $shipBay->penalty ?? 0,
                            'total_revenue' => $shipBay->total_revenue,
                        ];

                        $this->simulationLogController->createLogEntry($logData);
                    }

                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Failed to create ship docks',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }

            $room->status = $request->status;
            $room->save();

            $cacheKey = "containers:room:{$room->id}";
            Redis::del($cacheKey);

            return response()->json($room);
        }

        $validated = $request->validate([
            'name' => 'string',
            'description' => 'string',
            'total_rounds' => 'integer|min:1',
            'cards_limit_per_round' => 'integer|min:1',
            'cards_must_process_per_round' => 'integer|min:1',
            'restowage_cost' => 'integer|min:1',
            'move_cost' => 'integer|min:1',
            'assigned_users' => 'array',
            'assigned_users.*' => 'exists:users,id',
            'deck' => 'exists:decks,id',
            'ship_layout' => 'exists:ship_layouts,id',
            'dock_warehouse_costs' => 'array',
            'dock_warehouse_costs.dry.committed' => 'integer|min:0',
            'dock_warehouse_costs.dry.non_committed' => 'integer|min:0',
            'dock_warehouse_costs.reefer.committed' => 'integer|min:0',
            'dock_warehouse_costs.reefer.non_committed' => 'integer|min:0',
            'swap_config' => 'array',
            // 'extra_moves_cost' => 'integer|min:1',
            // 'ideal_crane_split' => 'integer|min:1',
        ]);
        // if (isset($validated['extra_moves_cost'])) $room->extra_moves_cost = $validated['extra_moves_cost'];
        // if (isset($validated['ideal_crane_split'])) $room->ideal_crane_split = $validated['ideal_crane_split'];

        if (isset($validated['name'])) $room->name = $validated['name'];
        if (isset($validated['description'])) $room->description = $validated['description'];
        if (isset($validated['total_rounds'])) $room->total_rounds = $validated['total_rounds'];
        if (isset($validated['cards_limit_per_round'])) $room->cards_limit_per_round = $validated['cards_limit_per_round'];
        if (isset($validated['cards_must_process_per_round'])) $room->cards_must_process_per_round = $validated['cards_must_process_per_round'];
        if (isset($validated['move_cost'])) $room->move_cost = $validated['move_cost'];
        if (isset($validated['restowage_cost'])) $room->restowage_cost = $validated['restowage_cost'];

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

        $dockWarehouseCostsUpdated = isset($validated['dock_warehouse_costs']) ||
            isset($validated['dock_warehouse_costs.dry.committed']) ||
            isset($validated['dock_warehouse_costs.dry.non_committed']) ||
            isset($validated['dock_warehouse_costs.reefer.committed']) ||
            isset($validated['dock_warehouse_costs.reefer.non_committed']);

        if ($dockWarehouseCostsUpdated) {
            if (isset($validated['dock_warehouse_costs'])) {
                $room->dock_warehouse_costs = $validated['dock_warehouse_costs'];
            } else {
                $currentCosts = $room->dock_warehouse_costs;

                if (isset($validated['dock_warehouse_costs.dry.committed'])) {
                    $currentCosts['dry']['committed'] = $validated['dock_warehouse_costs.dry.committed'];
                }
                if (isset($validated['dock_warehouse_costs.dry.non_committed'])) {
                    $currentCosts['dry']['non_committed'] = $validated['dock_warehouse_costs.dry.non_committed'];
                }
                if (isset($validated['dock_warehouse_costs.reefer.committed'])) {
                    $currentCosts['reefer']['committed'] = $validated['dock_warehouse_costs.reefer.committed'];
                }
                if (isset($validated['dock_warehouse_costs.reefer.non_committed'])) {
                    $currentCosts['reefer']['non_committed'] = $validated['dock_warehouse_costs.reefer.non_committed'];
                }

                $currentCosts['default'] = intval(($currentCosts['dry']['committed'] +
                    $currentCosts['dry']['non_committed'] +
                    $currentCosts['reefer']['committed'] +
                    $currentCosts['reefer']['non_committed']) / 4);

                $room->dock_warehouse_costs = $currentCosts;
            }
        }

        if (isset($validated['swap_config'])) $room->swap_config = json_encode($validated['swap_config']);

        $room->save();

        return response()->json($room);
    }

    public function destroy(Request $request, Room $room)
    {
        if ($room->status === 'active') {
            return response()->json([
                'message' => 'Cannot delete an active room'
            ], 403);
        }

        try {
            $this->redisService->deleteAllRoomCacheKeys($room->id);

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

        if ($room->status === 'finished') {
            return response()->json([
                'message' => 'Simulation is already completed',
                'simulation_completed' => true
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

    public function calculateRestowagePenalties($room, $userId)
    {
        $shipBay = ShipBay::where('room_id', $room->id)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return [
                'penalty' => 0,
                'moves' => 0,
                'containers' => [],
                'container_count' => 0
            ];
        }

        // Get the port sequence from swap configuration
        $swapConfig = json_decode($room->swap_config, true);
        if (empty($swapConfig)) {
            return [
                'penalty' => 0,
                'moves' => 0,
                'containers' => [],
                'container_count' => 0
            ];
        }

        // Get the current port
        $currentPort = $shipBay->port;

        // ENHANCEMENT: Identify all port sequences from the swap config
        $portSequences = [];
        $visitedPorts = [];

        // First, identify all starting ports (could be multiple chains)
        $startingPorts = array_keys($swapConfig);

        // For each starting port, build its sequence
        foreach ($startingPorts as $startPort) {
            if (isset($visitedPorts[$startPort])) continue;

            $sequence = [];
            $nextPort = $startPort;
            $chainVisited = [$startPort => true];

            // Follow the chain until we reach a port we've already visited
            while (isset($swapConfig[$nextPort]) && !isset($chainVisited[$swapConfig[$nextPort]])) {
                $nextPort = $swapConfig[$nextPort];
                $sequence[] = $nextPort;
                $chainVisited[$nextPort] = true;
                $visitedPorts[$nextPort] = true;
            }

            if (!empty($sequence)) {
                $portSequences[$startPort] = $sequence;
            }
        }

        // Now identify the specific sequence that contains our current port
        $portSequence = [];
        $foundInSequence = false;

        // First, check if current port is a starting port
        if (isset($portSequences[$currentPort])) {
            $portSequence = $portSequences[$currentPort];
            $foundInSequence = true;
        } else {
            // Check if current port is in any sequence
            foreach ($portSequences as $startPort => $sequence) {
                $index = array_search($currentPort, $sequence);
                if ($index !== false) {
                    // Reorder sequence to start from current port
                    $portSequence = array_merge(
                        array_slice($sequence, $index + 1),
                        [$startPort],
                        array_slice($sequence, 0, $index)
                    );
                    $foundInSequence = true;
                    break;
                }
            }
        }

        // If port not found in any sequence, fallback to original method
        if (!$foundInSequence) {
            $nextPort = $currentPort;
            $visited = [$currentPort => true];

            while (isset($swapConfig[$nextPort]) && !isset($visited[$swapConfig[$nextPort]])) {
                $nextPort = $swapConfig[$nextPort];
                $portSequence[] = $nextPort;
                $visited[$nextPort] = true;
            }
        }

        // Get all containers in the bay
        $arena = is_array($shipBay->arena) ? $shipBay->arena : json_decode($shipBay->arena, true);
        if (!isset($arena['containers']) || empty($arena['containers'])) {
            return [
                'penalty' => 0,
                'moves' => 0,
                'containers' => [],
                'container_count' => 0
            ];
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
        $portPriority = [$currentPort => 0]; // Current port has highest priority (lowest number)
        foreach ($portSequence as $index => $port) {
            $portPriority[$port] = $index + 1; // Other ports have priorities starting from 1
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

                // ENHANCEMENT: Handle containers with destinations outside our direct sequence
                // If the destination is not in our port priority map, check if it's in any sequence
                if (!isset($portPriority[$targetDestination])) {
                    // Container with destination in another sequence chain should be
                    // considered as a lowest priority (will be visited last)
                    $portPriority[$targetDestination] = PHP_INT_MAX;

                    // Check if this destination exists in any sequence
                    foreach ($portSequences as $startPort => $sequence) {
                        if (in_array($targetDestination, $sequence) || $startPort === $targetDestination) {
                            // Found in another sequence - assign a high priority number
                            // but still need to check for stacking violations
                            $portPriority[$targetDestination] = 1000; // Arbitrary high number, but not MAX
                            break;
                        }
                    }
                }

                // Check for containers above that need to be moved
                $blockingContainers = [];

                for ($i = 0; $i < $j; $i++) {
                    $topContainer = $containers[$i];
                    $topDestination = $topContainer['destination'];

                    if (!$topDestination || $topDestination === $currentPort) {
                        continue;
                    }

                    // ENHANCEMENT: Handle top containers with destinations outside our direct sequence
                    if (!isset($portPriority[$topDestination])) {
                        // See if this top container's destination is in any sequence
                        $found = false;
                        foreach ($portSequences as $startPort => $sequence) {
                            if (in_array($topDestination, $sequence) || $startPort === $topDestination) {
                                $portPriority[$topDestination] = 1000; // Arbitrary high number
                                $found = true;
                                break;
                            }
                        }

                        if (!$found) {
                            // If not found in any sequence, assign maximum priority
                            $portPriority[$topDestination] = PHP_INT_MAX;
                        }
                    }

                    // If top container's port visit is LATER than target container's
                    if ($portPriority[$targetDestination] < $portPriority[$topDestination]) {
                        $blockingContainers[] = $topContainer;

                        // Track this container as needing to be moved
                        $containersToMoveByStack[$stackKey][$topContainer['id']] = $topContainer;
                    }
                }

                if (!empty($blockingContainers)) {
                    $isNextPort = !empty($portSequence) && ($targetDestination === $portSequence[0]);

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
        $restowagePenalty = $restowageMoves * $room->restowage_cost;


        return [
            'penalty' => $restowagePenalty,
            'moves' => $restowageMoves,
            'containers' => $restowageContainers,
            'move_details' => $moveDetails,
            'container_count' => $totalContainersToMove,
            'restowage_moves' => $restowageMoves
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
        $userProcessedCounts = [];

        // Iterate through each user and swap their bays
        foreach ($userIds as $userId) {
            $shipBay = ShipBay::where('room_id', $room->id)
                ->where('user_id', $userId)
                ->first();

            // Store the processed cards count for later use
            $userProcessedCounts[$userId] = $shipBay->processed_cards;

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

            // Calculate unrolled penalties for cards not handled in this round
            $unrolledCardsContainers = $this->calculateUnrolledPenalties($room, $userId, $shipBay, $shipBay->current_round);
            $shipBay->unrolled_penalty = $unrolledCardsContainers['penalty'];
            $shipBay->unrolled_cards = $unrolledCardsContainers['unrolled_cards'];

            // // Calculate penalties for containers sitting in dock
            $dockWarehouseDetails = $this->calculateDockWarehouse($room, $userId, $shipBay->current_round + 1);
            $shipBay->dock_warehouse_penalty = $dockWarehouseDetails['penalty'];
            $shipBay->dock_warehouse_containers = $dockWarehouseDetails['dock_warehouse_containers'];

            // Calculate restowage penalties
            $restowageDetails = $this->calculateRestowagePenalties($room, $userId);
            $shipBay->restowage_penalty = $restowageDetails['penalty'];
            $shipBay->restowage_moves = $restowageDetails['moves'];
            $shipBay->restowage_containers = $restowageDetails['containers'];

            // Update total penalty
            $shipBay->penalty = $shipBay->penalty + ($shipBay->dock_warehouse_penalty ?? 0) + ($shipBay->restowage_penalty ?? 0) + ($shipBay->unrolled_penalty ?? 0);

            // Update total revenue calculation (subtract penalties)
            $shipBay->total_revenue = ($shipBay->revenue ?? 0) - ($shipBay->penalty ?? 0);

            $shipBay->save();
            $this->finalizeWeeklyPerformance($room, $userId, $shipBay->current_round);
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
                'bay_moves' => $bay->bay_moves,
                'restowage_moves' => $bay->restowage_moves ?? 0,
                'restowage_penalty' => $bay->restowage_penalty ?? 0,
                'created_at' => now(),
                'updated_at' => now()
                // 'bay_pairs' => $bay->bay_pairs,
                // 'long_crane_moves' => $bay->long_crane_moves,
                // 'extra_moves_on_long_crane' => $bay->extra_moves_on_long_crane,
            ]);

            // Reset statistics for the new week
            $bay->discharge_moves = 0;
            $bay->load_moves = 0;
            $bay->restowage_moves = 0;
            $bay->bay_moves = json_encode([]);
            $bay->restowage_containers = null;

            $bay->dock_warehouse_penalty = 0;
            $bay->restowage_penalty = 0;
            $bay->unrolled_penalty = 0;
            $bay->save();
            // $bay->bay_pairs = json_encode([]);
            // $bay->long_crane_moves = 0;
            // $bay->extra_moves_on_long_crane = 0;
        }

        // Now reload the bays to get the updated arenas (with restowage containers removed)
        $shipBays = ShipBay::whereIn('user_id', $userIds)
            ->where('room_id', $room->id)
            ->get();

        // Create maps for lookup
        $baysByPort = [];
        $originalArenas = [];
        $portSequences = [];

        foreach ($shipBays as $bay) {
            $baysByPort[$bay->port] = $bay;
            $originalArenas[$bay->port] = $bay->arena;

            // Build port sequence for each port for proper ordering later
            $portSequence = [];
            $currentPort = $bay->port;
            $visited = [$currentPort => true];

            while (isset($swapConfig[$currentPort]) && !isset($visited[$swapConfig[$currentPort]])) {
                $nextPort = $swapConfig[$currentPort];
                $portSequence[] = $nextPort;
                $visited[$nextPort] = true;
                $currentPort = $nextPort;
            }

            $portSequences[$bay->port] = $portSequence;
        }

        // Perform the swaps according to the configuration
        foreach ($swapConfig as $fromPort => $toPort) {
            if (isset($baysByPort[$fromPort]) && isset($originalArenas[$toPort])) {
                $sourceBay = $baysByPort[$toPort];
                // $sourceBay->arena = $originalArenas[$fromPort];
                // Get the original arena data from the sending port
                $arenaData = is_string($originalArenas[$fromPort])
                    ? json_decode($originalArenas[$fromPort], true)
                    : $originalArenas[$fromPort];

                // Get the port sequence to use for reordering
                $receivingPort = $toPort;
                $properPortSequence = [$receivingPort, ...$portSequences[$receivingPort]];

                // Reorder container stacks based on port priority
                if (isset($arenaData['containers']) && !empty($arenaData['containers'])) {
                    // Group containers by stack position (bay-column)
                    $stacks = [];
                    foreach ($arenaData['containers'] as $container) {
                        if (!isset($container['bay']) || !isset($container['row']) || !isset($container['col'])) continue;

                        $stackKey = $container['bay'] . '-' . $container['col'];
                        if (!isset($stacks[$stackKey])) {
                            $stacks[$stackKey] = [];
                        }

                        // Find container's destination
                        $containerObj = Container::find($container['id']);
                        if ($containerObj && $containerObj->card) {
                            $destination = $containerObj->card->destination;

                            // Add destination to container data for sorting
                            $container['destination'] = $destination;
                            $stacks[$stackKey][] = $container;
                        } else {
                            // If we can't determine destination, still keep the container
                            $container['destination'] = null;
                            $stacks[$stackKey][] = $container;
                        }
                    }

                    // Sort each stack by port visit priority (proper order)
                    $reorderedContainers = [];
                    foreach ($stacks as $stackKey => $stackContainers) {
                        if (empty($stackContainers)) continue;

                        // Sort containers by port sequence (furthest ports first - for bottom placement)
                        usort($stackContainers, function ($a, $b) use ($properPortSequence, $receivingPort) {
                            // Default cases
                            if (!$a['destination']) return 1;  // Unknown goes to bottom
                            if (!$b['destination']) return -1; // Unknown goes to bottom

                            // Current port goes to bottom
                            if ($a['destination'] === $receivingPort && $b['destination'] !== $receivingPort) return 1;
                            if ($b['destination'] === $receivingPort && $a['destination'] !== $receivingPort) return -1;

                            // Sort based on port sequence
                            $aIndex = array_search($a['destination'], $properPortSequence);
                            $bIndex = array_search($b['destination'], $properPortSequence);

                            // If port not in sequence, put at bottom
                            if ($aIndex === false) $aIndex = PHP_INT_MAX;
                            if ($bIndex === false) $bIndex = PHP_INT_MAX;

                            // REVERSED comparison to put furthest ports at bottom
                            return $bIndex - $aIndex;
                        });

                        // Get original bay and column from stack key
                        list($bay, $col) = explode('-', $stackKey);
                        $bay = (int)$bay;
                        $col = (int)$col;

                        // Get bay size for proper positioning
                        $bayRows = $baySize['rows'] ?? 4;

                        // Reassign row positions from BOTTOM to TOP
                        $rowCount = count($stackContainers);
                        for ($i = 0; $i < $rowCount; $i++) {
                            $container = $stackContainers[$i];

                            // Calculate row position - place containers from bottom up
                            // Last container (i=rowCount-1) goes to top row (0)
                            // First container (i=0) goes to bottom row (bayRows-1)
                            $rowPosition = $bayRows - 1 - $i;

                            // Make sure we don't create negative row positions
                            if ($rowPosition < 0) $rowPosition = 0;

                            $container['row'] = $rowPosition;
                            $reorderedContainers[] = $container;
                        }
                    }

                    // Update the arena with reordered containers
                    $arenaData['containers'] = $reorderedContainers;
                }

                // Save the reordered arena to the destination bay
                $sourceBay->arena = is_array($arenaData) ? json_encode($arenaData) : $arenaData;
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

        // Get the cards_limit_per_round setting
        $cardsLimitPerRound = $room->cards_limit_per_round;
        $newRound = ShipBay::where('room_id', $room->id)->first()->current_round;

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
            // Get the ship bay for this user
            $shipBay = ShipBay::where('room_id', $room->id)
                ->where('user_id', $userId)
                ->first();

            if (!$shipBay) continue;

            // Get the minimum number of cards that must be processed
            $cardsMustProcess = $room->cards_must_process_per_round;

            // Use processed_cards directly from shipBay
            $processedCount = $userProcessedCounts[$userId];

            // Only create backlogs if user hasn't processed the minimum required cards
            if ($processedCount < $cardsMustProcess) {
                // Calculate how many more cards needed to meet the minimum requirement
                $cardsNeeded = $cardsMustProcess - $processedCount;

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
                    ->limit($cardsNeeded) // Only take the number of cards needed to meet the requirement
                    ->get();

                // Mark these cards as backlog and keep them in the current round
                foreach ($userUnhandledCards as $card) {
                    $card->is_backlog = true;
                    $card->original_round = $card->round;
                    $card->round = $newRound; // Move to current round
                    $card->save();
                }

                $unhandledCards = array_merge($unhandledCards, $userUnhandledCards->toArray());
            } else {
                // If user processed the required minimum, don't create backlog
                // Just update any selected cards to have null round so they don't appear again
                CardTemporary::where([
                    'user_id' => $userId,
                    'room_id' => $room->id,
                    'status' => 'selected',
                ])
                    ->where('round', '<', $newRound)
                    ->update(['round' => null]);
            }
        }

        foreach ($userIds as $userId) {
            $shipBay = ShipBay::where('room_id', $room->id)
                ->where('user_id', $userId)
                ->first();

            $shipDock = ShipDock::where('room_id', $room->id)
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

            WeeklyPerformance::updateOrCreate(
                [
                    'room_id' => $room->id,
                    'user_id' => $userId,
                    'week' => $newRound
                ],
                [
                    'discharge_moves' => 0,
                    'load_moves' => 0,
                    'restowage_container_count' => 0,
                    'restowage_moves' => 0,
                    'restowage_penalty' => 0,
                    'unrolled_container_counts' => json_encode([
                        'dry_committed' => 0,
                        'dry_non_committed' => 0,
                        'reefer_committed' => 0,
                        'reefer_non_committed' => 0,
                        'total' => 0
                    ]),
                    'dock_warehouse_container_counts' => json_encode([
                        'dry_committed' => 0,
                        'dry_non_committed' => 0,
                        'reefer_committed' => 0,
                        'reefer_non_committed' => 0,
                        'total' => 0
                    ]),
                    'total_penalty' => 0,
                    'dock_warehouse_penalty' => 0,
                    'unrolled_penalty' => 0,
                    'revenue' => 0,
                    'move_costs' => 0,
                    'net_result' => 0,
                    'dry_containers_loaded' => 0,
                    'reefer_containers_loaded' => 0
                ]
            );

            $logData = [
                'user_id' => $userId,
                'room_id' => $room->id,
                'arena_bay' => $shipBay->arena,
                'arena_dock' => $shipDock->arena,
                'port' => $shipBay->port,
                'section' => $shipBay->section,
                'round' => $shipBay->current_round,
                'revenue' => $shipBay->revenue ?? 0,
                'penalty' => $shipBay->penalty ?? 0,
                'total_revenue' => $shipBay->total_revenue,
            ];

            $this->simulationLogController->createLogEntry($logData);
        }

        return response()->json([
            'message' => 'Bays swapped successfully',
        ]);
    }

    public function finalizeWeeklyPerformance(Room $room, $userId, $week)
    {
        // Get ship bay data
        $shipBay = ShipBay::where('room_id', $room->id)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            // Create an empty record if no ship bay exists
            return WeeklyPerformance::updateOrCreate(
                [
                    'room_id' => $room->id,
                    'user_id' => $userId,
                    'week' => $week
                ],
                [
                    'discharge_moves' => 0,
                    'load_moves' => 0,
                    'restowage_container_count' => 0,
                    'restowage_moves' => 0,
                    'restowage_penalty' => 0,
                    'unrolled_container_counts' => json_encode([
                        'dry_committed' => 0,
                        'dry_non_committed' => 0,
                        'reefer_committed' => 0,
                        'reefer_non_committed' => 0,
                        'total' => 0
                    ]),
                    'dock_warehouse_container_counts' => json_encode([
                        'dry_committed' => 0,
                        'dry_non_committed' => 0,
                        'reefer_committed' => 0,
                        'reefer_non_committed' => 0,
                        'total' => 0
                    ]),
                    'total_penalty' => 0,
                    'dock_warehouse_penalty' => 0,
                    'unrolled_penalty' => 0,
                    'revenue' => 0,
                    'move_costs' => 0,
                    'net_result' => 0,
                    'dry_containers_loaded' => 0,
                    'reefer_containers_loaded' => 0
                ]
            );
        }

        // Calculate container counts from arena
        $arenaData = is_string($shipBay->arena) ? json_decode($shipBay->arena, true) : $shipBay->arena;
        $containerIds = [];

        if (isset($arenaData['containers'])) {
            foreach ($arenaData['containers'] as $container) {
                if (isset($container['id'])) {
                    $containerIds[] = $container['id'];
                }
            }
        }

        $containers = Container::whereIn('id', $containerIds)
            ->where('deck_id', $room->deck_id)
            ->get();
        $dryContainersLoaded = $containers->where('type', 'dry')->count();
        $reeferContainersLoaded = $containers->where('type', 'reefer')->count();

        // Get penalty data from ShipBay
        $dockWarehousePenalty = $shipBay->dock_warehouse_penalty ?? 0;
        $unrolledPenalty = $shipBay->unrolled_penalty ?? 0;
        $restowagePenalty = $shipBay->restowage_penalty ?? 0;
        $restowageMoves = $shipBay->restowage_moves ?? 0;

        // Process unrolled cards data
        $unrolledContainerCounts = [
            'dry_committed' => 0,
            'dry_non_committed' => 0,
            'reefer_committed' => 0,
            'reefer_non_committed' => 0,
            'total' => 0
        ];

        // Handle already-decoded array OR JSON string
        $unrolledCards = $shipBay->unrolled_cards;
        if (is_string($unrolledCards)) {
            $unrolledCards = json_decode($unrolledCards, true);
        }
        $unrolledCards = $unrolledCards ?? [];

        foreach ($unrolledCards as $card) {
            if (isset($card['type']) && isset($card['priority']) && isset($card['quantity'])) {
                $type = strtolower($card['type']);
                $isCommitted = $card['priority'] === 'Committed';
                $quantity = $card['quantity'];

                if ($type === 'dry' && $isCommitted) {
                    $unrolledContainerCounts['dry_committed'] += $quantity;
                } elseif ($type === 'dry' && !$isCommitted) {
                    $unrolledContainerCounts['dry_non_committed'] += $quantity;
                } elseif ($type === 'reefer' && $isCommitted) {
                    $unrolledContainerCounts['reefer_committed'] += $quantity;
                } elseif ($type === 'reefer' && !$isCommitted) {
                    $unrolledContainerCounts['reefer_non_committed'] += $quantity;
                }

                $unrolledContainerCounts['total'] += $quantity;
            }
        }

        // Process dock warehouse containers data
        $dockWarehouseCountsArray = [
            'dry_committed' => 0,
            'dry_non_committed' => 0,
            'reefer_committed' => 0,
            'reefer_non_committed' => 0,
            'total' => 0
        ];

        // Handle already-decoded array OR JSON string
        $dockWarehouseContainers = $shipBay->dock_warehouse_containers;
        if (is_string($dockWarehouseContainers)) {
            $dockWarehouseContainers = json_decode($dockWarehouseContainers, true);
        }
        $dockWarehouseContainers = $dockWarehouseContainers ?? [];

        foreach ($dockWarehouseContainers as $container) {
            if (isset($container['type']) && isset($container['is_committed'])) {
                $type = strtolower($container['type']);
                $isCommitted = $container['is_committed'];

                if ($type === 'dry' && $isCommitted) {
                    $dockWarehouseCountsArray['dry_committed']++;
                } elseif ($type === 'dry' && !$isCommitted) {
                    $dockWarehouseCountsArray['dry_non_committed']++;
                } elseif ($type === 'reefer' && $isCommitted) {
                    $dockWarehouseCountsArray['reefer_committed']++;
                } elseif ($type === 'reefer' && !$isCommitted) {
                    $dockWarehouseCountsArray['reefer_non_committed']++;
                }

                $dockWarehouseCountsArray['total']++;
            }
        }

        // Calculate restowage container count
        $restowageContainers = $shipBay->restowage_containers;
        if (is_string($restowageContainers)) {
            $restowageContainers = json_decode($restowageContainers, true);
        }
        $restowageContainerCount = is_array($restowageContainers) ? count($restowageContainers) : 0;

        // Calculate move costs
        $moveCost = $room->move_cost;
        $totalMoves = $shipBay->discharge_moves + $shipBay->load_moves;
        $movesPenalty = $totalMoves * $moveCost;

        // Get total penalty
        $totalPenalty = $shipBay->penalty ?? ($movesPenalty + $unrolledPenalty + $dockWarehousePenalty + $restowagePenalty);

        // Update weekly performance record with actual data from ShipBay
        return WeeklyPerformance::updateOrCreate(
            [
                'room_id' => $room->id,
                'user_id' => $userId,
                'week' => $week
            ],
            [
                'discharge_moves' => $shipBay->discharge_moves,
                'load_moves' => $shipBay->load_moves,
                'restowage_container_count' => $restowageContainerCount,
                'restowage_moves' => $restowageMoves,
                'restowage_penalty' => $restowagePenalty,
                'unrolled_container_counts' => json_encode($unrolledContainerCounts),
                'dock_warehouse_container_counts' => json_encode($dockWarehouseCountsArray),
                'total_penalty' => $totalPenalty,
                'dock_warehouse_penalty' => $dockWarehousePenalty,
                'unrolled_penalty' => $unrolledPenalty,
                'revenue' => $shipBay->revenue,
                'move_costs' => $movesPenalty,
                'net_result' => $shipBay->revenue - $totalPenalty,
                'dry_containers_loaded' => $dryContainersLoaded,
                'reefer_containers_loaded' => $reeferContainersLoaded
            ]
        );
    }

    /**
     * Calculate penalties for cards that were neither accepted nor rejected (unrolled)
     */
    public function calculateUnrolledPenalties(Room $room, $userId, $shipBay, $currentRound)
    {
        // Get the number of cards that must be processed
        $cardsMustProcess = $room->cards_must_process_per_round;

        // Use processed_cards directly from shipBay instead of querying
        $processedCount = $shipBay->processed_cards;

        // Get penalties from Market Intelligence
        $penalties = $this->getPenaltiesFromMarketIntelligence($room);

        $capacityUptake = CapacityUptake::where('room_id', $room->id)
            ->where('user_id', $userId)
            ->where('week', $currentRound)
            ->first();

        $rejectedCards = is_array($capacityUptake->rejected_cards) ?
            $capacityUptake->rejected_cards :
            json_decode($capacityUptake->rejected_cards ?? '[]', true);

        // If user has processed the required minimum, no penalties should be applied
        if ($processedCount >= $cardsMustProcess && count($rejectedCards) === 0) {
            return [
                'penalty' => 0,
                'unrolled_cards' => [],
                'rates' => [
                    'dry_committed' => $penalties['dry']['committed'],
                    'dry_non_committed' => $penalties['dry']['non_committed'],
                    'reefer_committed' => $penalties['reefer']['committed'],
                    'reefer_non_committed' => $penalties['reefer']['non_committed']
                ],
                'container_counts' => [
                    'dry_committed' => 0,
                    'dry_non_committed' => 0,
                    'reefer_committed' => 0,
                    'reefer_non_committed' => 0,
                    'total' => 0
                ]
            ];
        }

        // Find cards that were selected but not processed (neither accepted nor rejected)
        $unprocessedCards = CardTemporary::join('cards', function ($join) {
            $join->on('cards.id', '=', 'card_temporaries.card_id')
                ->on('cards.deck_id', '=', 'card_temporaries.deck_id');
        })
            ->where([
                'card_temporaries.user_id' => $userId,
                'card_temporaries.room_id' => $room->id,
                'card_temporaries.status' => 'selected',
                'card_temporaries.round' => $currentRound,
            ])
            ->where('cards.priority', 'Committed') // Hanya kartu committed
            ->select('card_temporaries.*', 'cards.type', 'cards.priority', 'cards.quantity', 'cards.revenue')
            ->limit(($cardsMustProcess - $processedCount))
            ->get();

        $rejectedNonCommittedCards = CardTemporary::join('cards', function ($join) {
            $join->on('cards.id', '=', 'card_temporaries.card_id')
                ->on('cards.deck_id', '=', 'card_temporaries.deck_id');
        })
            ->where([
                'card_temporaries.user_id' => $userId,
                'card_temporaries.room_id' => $room->id,
                'card_temporaries.status' => 'rejected',
                'card_temporaries.round' => $currentRound,
            ])
            ->where('cards.priority', '!=', 'Committed') // Hanya kartu non-committed
            ->select('card_temporaries.*', 'cards.type', 'cards.priority', 'cards.quantity', 'cards.revenue')
            ->get();

        $allPenalizedCards = $unprocessedCards->concat($rejectedNonCommittedCards);

        if ($allPenalizedCards->isEmpty()) {
            return [
                'penalty' => 0,
                'unrolled_cards' => [],
                'rates' => [
                    'dry_committed' => $penalties['dry']['committed'],
                    'dry_non_committed' => $penalties['dry']['non_committed'],
                    'reefer_committed' => $penalties['reefer']['committed'],
                    'reefer_non_committed' => $penalties['reefer']['non_committed']
                ],
                'container_counts' => [
                    'dry_committed' => 0,
                    'dry_non_committed' => 0,
                    'reefer_committed' => 0,
                    'reefer_non_committed' => 0,
                    'total' => 0
                ]
            ];
        }

        // Calculate penalty based on container type and commitment
        $totalPenalty = 0;
        $unrolledCards = [];

        // Initialize container counts
        $containerCounts = [
            'dry_committed' => 0,
            'dry_non_committed' => 0,
            'reefer_committed' => 0,
            'reefer_non_committed' => 0,
            'total' => 0
        ];

        foreach ($allPenalizedCards as $cardTemp) {
            $isCommitted = strtolower($cardTemp->priority) === 'committed';
            $isDry = strtolower($cardTemp->type) === 'dry';
            $quantity = $cardTemp->quantity ?? 1;

            // Determine the appropriate penalty rate
            $penaltyRate = 0;
            if ($isDry) {
                $penaltyRate = $isCommitted
                    ? $penalties['dry']['committed']
                    : $penalties['dry']['non_committed'];
            } else {
                $penaltyRate = $isCommitted
                    ? $penalties['reefer']['committed']
                    : $penalties['reefer']['non_committed'];
            }

            // Calculate penalty for this card
            $cardPenalty = $penaltyRate * $quantity;
            $totalPenalty += $cardPenalty;

            // Track unrolled cards for reporting
            $unrolledCards[] = [
                'card_id' => $cardTemp->card_id,
                'deck_id' => $cardTemp->deck_id,
                'type' => $cardTemp->type,
                'priority' => $cardTemp->priority,
                'quantity' => $quantity,
                'revenue' => $cardTemp->revenue,
                'penalty' => $cardPenalty,
                'penalty_rate' => $penaltyRate,
                'round' => $currentRound,
                'status' => $cardTemp->status
            ];

            // Update container counts
            if ($isDry && $isCommitted) {
                $containerCounts['dry_committed'] += $quantity;
            } elseif ($isDry && !$isCommitted) {
                $containerCounts['dry_non_committed'] += $quantity;
            } elseif (!$isDry && $isCommitted) {
                $containerCounts['reefer_committed'] += $quantity;
            } else {
                $containerCounts['reefer_non_committed'] += $quantity;
            }

            $containerCounts['total'] += $quantity;
        }

        return [
            'penalty' => $totalPenalty,
            'unrolled_cards' => $unrolledCards,
            'rates' => [
                'dry_committed' => $penalties['dry']['committed'],
                'dry_non_committed' => $penalties['dry']['non_committed'],
                'reefer_committed' => $penalties['reefer']['committed'],
                'reefer_non_committed' => $penalties['reefer']['non_committed']
            ],
            'container_counts' => $containerCounts
        ];
    }

    /**
     * Get penalties from Market Intelligence for the room's deck
     *
     * @param Room $room
     * @return array Penalties in the format expected by the penalty calculation
     */
    private function getPenaltiesFromMarketIntelligence(Room $room)
    {
        // Get deck ID from room
        $deckId = $room->deck_id;

        // Default penalties if Market Intelligence not found
        $defaultPenalties = [
            'default' => 7000000,
            'dry' => [
                'committed' => 8000000,
                'non_committed' => 4000000
            ],
            'reefer' => [
                'committed' => 15000000,
                'non_committed' => 9000000
            ]
        ];

        if (!$deckId) {
            return $room->dock_warehouse_costs ?? $defaultPenalties;
        }

        // Get Market Intelligence for this deck
        $marketIntelligence = MarketIntelligence::where('deck_id', $deckId)->first();

        if (!$marketIntelligence || !$marketIntelligence->penalties) {
            return $room->dock_warehouse_costs ?? $defaultPenalties;
        }

        $miPenalties = $marketIntelligence->penalties;

        // Convert Market Intelligence penalties format to the format needed by our code
        return [
            'default' => $miPenalties['default'] ?? 7000000,
            'dry' => [
                'committed' => $miPenalties['dry_committed'] ?? 8000000,
                'non_committed' => $miPenalties['dry_non_committed'] ?? 4000000
            ],
            'reefer' => [
                'committed' => $miPenalties['reefer_committed'] ?? 15000000,
                'non_committed' => $miPenalties['reefer_non_committed'] ?? 9000000
            ]
        ];
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

    public function calculateDockWarehouse($room, $userId, $currentRound)
    {
        // Get ship dock to find containers that weren't moved
        $shipDock = ShipDock::where('room_id', $room->id)
            ->where('user_id', $userId)
            ->first();

        if (!$shipDock) {
            return [
                'penalty' => 0,
                'dock_warehouse_containers' => [],
                'container_counts' => [
                    'dry_committed' => 0,
                    'dry_non_committed' => 0,
                    'reefer_committed' => 0,
                    'reefer_non_committed' => 0,
                    'total' => 0
                ]
            ];
        }

        // Parse arena data to get containers in dock
        $arenaData = json_decode($shipDock->arena, true);
        $dockContainers = isset($arenaData['containers']) ? $arenaData['containers'] : [];

        if (empty($dockContainers)) {
            return [
                'penalty' => 0,
                'dock_warehouse_containers' => [],
                'container_counts' => [
                    'dry_committed' => 0,
                    'dry_non_committed' => 0,
                    'reefer_committed' => 0,
                    'reefer_non_committed' => 0,
                    'total' => 0
                ]
            ];
        }

        // Build container ID list
        $containerIds = array_map(function ($container) {
            return $container['id'];
        }, $dockContainers);

        // Get dock warehouse costs from the room
        $dockWarehouseCosts = $room->dock_warehouse_costs;

        // Initialize container counts
        $containerCounts = [
            'dry_committed' => 0,
            'dry_non_committed' => 0,
            'reefer_committed' => 0,
            'reefer_non_committed' => 0,
            'total' => 0
        ];

        // Get current port for this user
        $shipBay = ShipBay::where('room_id', $room->id)
            ->where('user_id', $userId)
            ->first();

        $currentPort = $shipBay ? $shipBay->port : '';

        // Match containers with accepted cards
        $dockWarehouseContainers = [];
        $totalPenalty = 0;

        foreach ($containerIds as $containerId) {
            $container = Container::where('id', $containerId)
                ->where('deck_id', $room->deck_id)
                ->first();
            if (!$container) continue;

            $containerType = strtolower($container->type ?? 'dry');
            $isCommitted = $container->card && strtolower($container->card->priority ?? '') === 'committed';
            $priorityType = $isCommitted ? 'committed' : 'non_committed';

            // Skip penalty for restowed containers
            // if ($container->is_restowed) {
            //     continue;
            // }

            if (isset($dockWarehouseCosts[$containerType][$priorityType])) {
                $penalty = $dockWarehouseCosts[$containerType][$priorityType];
            } else {
                // Fallback to default value if specific config not found
                $penalty = $dockWarehouseCosts['default'] ?? 50000;
            }

            // Check if this container belongs to an accepted card
            $cardTemp = CardTemporary::where('room_id', $room->id)
                ->where('user_id', $userId)
                ->where('card_id', $container->card_id)
                ->where('deck_id', $container->deck_id)
                ->where('status', 'accepted')
                ->where('round', '<', $currentRound) // Only previous rounds
                ->first();

            $penaltyReason = '';
            $weeksPending = 0;

            if ($cardTemp) {
                // Calculate weeks the container has been sitting in dock
                $weeksPending = $currentRound - $cardTemp->round;

                if ($weeksPending > 0) {
                    $totalPenalty += $penalty;
                    $penaltyReason = 'Not loaded after acceptance';

                    // Update container counts
                    if ($containerType === 'dry' && $isCommitted) {
                        $containerCounts['dry_committed']++;
                    } elseif ($containerType === 'dry' && !$isCommitted) {
                        $containerCounts['dry_non_committed']++;
                    } elseif ($containerType === 'reefer' && $isCommitted) {
                        $containerCounts['reefer_committed']++;
                    } else {
                        $containerCounts['reefer_non_committed']++;
                    }
                    $containerCounts['total']++;
                }
            } else if ($container->card && $container->card->destination != $currentPort) {
                // Regular foreign container penalty
                $totalPenalty += $penalty;
                $penaltyReason = 'Foreign container detention';
                $weeksPending = 1;

                // Update container counts
                if ($containerType === 'dry' && $isCommitted) {
                    $containerCounts['dry_committed']++;
                } elseif ($containerType === 'dry' && !$isCommitted) {
                    $containerCounts['dry_non_committed']++;
                } elseif ($containerType === 'reefer' && $isCommitted) {
                    $containerCounts['reefer_committed']++;
                } else {
                    $containerCounts['reefer_non_committed']++;
                }
                $containerCounts['total']++;
            }

            if ($penaltyReason) {
                $dockWarehouseContainers[] = [
                    'container_id' => $containerId,
                    'card_id' => $container->card_id,
                    'destination' => $container->card ? $container->card->destination : null,
                    'origin' => $container->card ? $container->card->origin : null,
                    'weeks_pending' => $weeksPending,
                    'penalty' => $penalty,
                    'reason' => $penaltyReason,
                    'type' => $containerType,
                    'is_committed' => $isCommitted
                ];
            }
        }

        return [
            'penalty' => $totalPenalty,
            'dock_warehouse_containers' => $dockWarehouseContainers,
            'container_counts' => $containerCounts
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

        DB::beginTransaction();

        try {
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

            // Get the deck with its cards to assign to users
            $deck = Deck::with('cards')->find($room->deck_id);
            $deckCards = $deck ? $deck->cards : collect([]);

            foreach ($ports as $userId => $port) {
                $user = User::find($userId);

                if ($user) {
                    // Create empty arena and generate initial containers
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

                    // Create or update ship bay
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

                    // Create capacity uptake
                    CapacityUptake::updateOrCreate([
                        'user_id' => $userId,
                        'room_id' => $room->id,
                        'week' => 1,
                        'port' => $port
                    ], [
                        'arena_start' => $shipBay->arena,
                        'arena_end' => null
                    ]);

                    // Filter matched cards for this user's port
                    $matchedCards = $deckCards->where('origin', $port)->values()->map(function ($card) use ($room) {
                        return [
                            'card_id' => $card->id,
                            'deck_id' => $room->deck_id
                        ];
                    })->toArray();

                    // Use CardTemporaryController to create properly sorted cards
                    $cardTempController = new CardTemporaryController();
                    $batchRequest = new Request([
                        'room_id' => $room->id,
                        'user_id' => $userId,
                        'round' => 1,
                        'cards' => $matchedCards
                    ]);

                    // Call batchCreate method
                    $cardTempController->batchCreate($batchRequest);

                    // Initialize week 1 weekly performance
                    $this->finalizeWeeklyPerformance($room, $userId, 1);

                    $shipBays[] = $shipBay;
                }
            }

            DB::commit();

            return response()->json(
                [
                    'message' => 'Ports set and cards assigned successfully',
                    'ports' => $ports,
                    'shipbays' => $shipBays,
                ],
                200
            );
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to set ports and assign cards',
                'error' => $e->getMessage()
            ], 500);
        }
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
                } catch (Exception $e) {
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

        return response()->json($cardTemporary, 200);
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
                    'dock_warehouse_penalty' => $shipBay->dock_warehouse_penalty,
                    'restowage_penalty' => $shipBay->restowage_penalty,
                    'total_revenue' => $shipBay->total_revenue,
                    'discharge_moves' => $shipBay->discharge_moves,
                    'load_moves' => $shipBay->load_moves,
                    'accepted_cards' => $shipBay->accepted_cards,
                    'rejected_cards' => $shipBay->rejected_cards,
                    // 'extra_moves_penalty' => $shipBay->extra_moves_penalty,
                    // 'long_crane_moves' => $shipBay->long_crane_moves,
                    // 'extra_moves_on_long_crane' => $shipBay->extra_moves_on_long_crane
                ];
            }

            // Sort rankings by total_revenue in descending order
            usort($rankings, function ($a, $b) {
                return $b['total_revenue'] <=> $a['total_revenue'];
            });

            return response()->json($rankings);
        } catch (Exception $e) {
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

    /**
     * Get consolidated room details for frontend
     */
    public function getRoomDetails($roomId)
    {
        // Find the room with essential relationships
        $room = Room::with(['admin', 'deck', 'shipLayout'])->findOrFail($roomId);

        // Get users in the room
        $userIds = json_decode($room->users ?? '[]');
        $users = User::whereIn('id', $userIds)->get();

        // Get deck origins
        $deckOrigins = [];
        if ($room->deck) {
            $deckOrigins = $room->deck->cards()
                ->where('is_initial', false)
                ->pluck('origin')
                ->unique()
                ->values();
        }

        // Get user ports and current round
        $shipBays = ShipBay::where('room_id', $roomId)
            ->select('user_id', 'port', 'current_round')
            ->get();

        // Format port assignments
        $portAssignments = [];
        foreach ($shipBays as $shipBay) {
            $portAssignments[$shipBay->user_id] = $shipBay->port;
        }

        // Get current round if room is active
        $currentRound = 1;
        if ($room->status === 'active' && $shipBays->isNotEmpty()) {
            $currentRound = $shipBays->first()->current_round;
        }

        // Get available users for admin
        $availableUsers = [];
        if ($room->admin_id) {
            $availableUsers = User::where('is_admin', false)
                ->where('status', 'active')
                ->select('id', 'name')
                ->get();
        }

        // Process swap config
        $swapConfig = $room->swap_config;

        // Prepare response
        return response()->json([
            'room' => $room,
            'admin' => [
                'id' => $room->admin->id,
                'name' => $room->admin->name
            ],
            'users' => $users,
            'deckOrigins' => $deckOrigins,
            'portAssignments' => $portAssignments,
            'currentRound' => $currentRound,
            'portsSet' => !empty($portAssignments),
            'availableUsers' => $availableUsers,
            'swapConfig' => $swapConfig
        ]);
    }

    public function getBayCapacityStatus($roomId, $userId)
    {
        // Get ship bay and room data
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json([
                'is_full' => false,
                'current_containers' => 0,
                'max_capacity' => 0,
                'usage_percentage' => 0
            ]);
        }

        $room = Room::find($roomId);
        if (!$room) {
            return response()->json([
                'is_full' => false,
                'current_containers' => 0,
                'max_capacity' => 0,
                'usage_percentage' => 0
            ]);
        }

        // Calculate maximum capacity from bay configuration
        $baySize = json_decode($room->bay_size, true);
        $bayCount = $room->bay_count;
        $maxCapacity = $baySize['rows'] * $baySize['columns'] * $bayCount;

        // Count containers in the bay
        $arenaData = is_string($shipBay->arena) ? json_decode($shipBay->arena, true) : $shipBay->arena;
        $currentContainers = isset($arenaData['containers']) ? count($arenaData['containers']) : 0;

        // Calculate usage percentage
        $usagePercentage = $maxCapacity > 0 ? ($currentContainers / $maxCapacity) * 100 : 0;

        // Determine if the bay is full (allowing a small buffer for operations)
        $isFull = $usagePercentage == 100;

        return response()->json([
            'is_full' => $isFull,
            'current_containers' => $currentContainers,
            'max_capacity' => $maxCapacity,
            'usage_percentage' => $usagePercentage
        ]);
    }
}
