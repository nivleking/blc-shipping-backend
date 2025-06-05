<?php

namespace App\Http\Controllers;

use App\Models\CapacityUptake;
use App\Models\Card;
use App\Models\CardTemporary;
use App\Models\Container;
use App\Models\Room;
use App\Models\ShipBay;
use App\Models\ShipDock;
use App\Models\SimulationLog;
use App\Models\WeeklyPerformance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipBayController extends Controller
{
    /**
     * Get consolidated arena data in a single API call
     */
    public function getConsolidatedArenaData($roomId, $userId)
    {
        try {
            // 1. Get room data
            $room = Room::find($roomId);
            if (!$room) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            // Get swap configuration
            $swapConfig = $room->swap_config ?
                (is_string($room->swap_config) ? json_decode($room->swap_config, true) : $room->swap_config)
                : [];

            // 2. Get ship bay data
            $shipBay = ShipBay::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->first();

            // 3. Get config (bay configuration)
            $baySize = json_decode($room->bay_size, true);
            $bayCount = $room->bay_count;
            $bayTypes = json_decode($room->bay_types, true);

            // 4. Get all containers
            // Get cards to know which cards are relevant for this room
            // $cardsByRoomId = Card::where('deck_id', $room->deck_id);

            // // Get the relevant card IDs from the cards
            // $relevantCardIds = $cardsByRoomId->pluck('id')->toArray();

            // // Get all containers for these cards, filtered by deck_id
            // $containers = Container::whereIn('card_id', $relevantCardIds)
            //     ->where('deck_id', $room->deck_id)
            //     ->get();

            // 5. Get ship dock data
            $shipDock = ShipDock::where('user_id', $userId)
                ->where('room_id', $roomId)
                ->first();

            // 6. Get restowage status
            $roomController = new RoomController();
            $restowageResponse = $roomController->calculateRestowagePenalties($room, $userId);

            // 7. Get bay capacity status
            $bayCapacityResponse = $roomController->getBayCapacityStatus($roomId, $userId);
            $isBayFull = $bayCapacityResponse->original['is_full'] ?? false;

            // 8. Get unfulfilled containers
            $cardTemporaries = CardTemporary::where([
                'room_id' => $roomId,
                'user_id' => $userId,
                'status' => 'accepted',
            ])
                ->whereNotNull('unfulfilled_containers')
                ->where('unfulfilled_containers', '!=', '[]')
                ->get();

            $unfulfilledContainers = [];
            foreach ($cardTemporaries as $card) {
                $unfulfilledContainers[$card->card_id] = $card->unfulfilled_containers;
            }

            // 9. Extract container IDs from arenas and get their destinations
            $containerIds = [];

            // Extract from bay arena
            if ($shipBay && $shipBay->arena) {
                $bayArena = is_string($shipBay->arena) ? json_decode($shipBay->arena, true) : $shipBay->arena;

                // Handle flat format with containers array
                if (isset($bayArena['containers'])) {
                    foreach ($bayArena['containers'] as $container) {
                        if (isset($container['id'])) {
                            $containerIds[] = $container['id'];
                        }
                    }
                }
                // Handle older 2D array format
                else if (is_array($bayArena)) {
                    foreach ($bayArena as $bay) {
                        if (is_array($bay)) {
                            foreach ($bay as $row) {
                                if (is_array($row)) {
                                    foreach ($row as $containerId) {
                                        if ($containerId) {
                                            $containerIds[] = $containerId;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Extract from dock arena
            if ($shipDock && $shipDock->arena) {
                $dockArena = is_string($shipDock->arena) ? json_decode($shipDock->arena, true) : $shipDock->arena;

                // Handle flat format with containers array
                if (isset($dockArena['containers'])) {
                    foreach ($dockArena['containers'] as $container) {
                        if (isset($container['id'])) {
                            $containerIds[] = $container['id'];
                        }
                    }
                }
                // Handle older format (array of positions)
                else if (is_array($dockArena)) {
                    foreach ($dockArena as $position => $containerId) {
                        if ($containerId) {
                            $containerIds[] = $containerId;
                        }
                    }
                }
            }

            // Get container destinations (same logic as ContainerController::getContainerDestinations)
            $containerDestinations = [];
            if (!empty($containerIds)) {
                $containersWithDestinations = Container::whereIn('id', $containerIds)
                    ->where('deck_id', $room->deck_id)
                    ->get();

                foreach ($containersWithDestinations as $container) {
                    if ($container->card) {
                        $containerDestinations[$container->id] = $container->card->destination;
                    }
                }
            }

            // Prepare consolidated response
            $response = [
                // Room data
                'room' => [
                    'deck_id' => $room->deck_id,
                    'move_cost' => $room->move_cost,
                    'dock_warehouse_cost' => $room->dock_warehouse_costs['default'] ?? 7000000,
                    'restowage_cost' => $room->restowage_cost,
                    'cards_must_process_per_round' => $room->cards_must_process_per_round,
                    'cards_limit_per_round' => $room->cards_limit_per_round,
                    'total_rounds' => $room->total_rounds,
                    'swap_config' => $swapConfig,
                ],

                // Ship bay data
                'ship_bay' => [
                    'current_round' => $shipBay->current_round,
                    'processed_cards' => $shipBay->processed_cards,
                    'arena' => $shipBay->arena,
                    'revenue' => $shipBay->revenue,
                    'port' => $shipBay->port,
                    'section' => $shipBay->section,
                    'load_moves' => $shipBay->load_moves,
                    'discharge_moves' => $shipBay->discharge_moves,
                    'accepted_cards' => $shipBay->accepted_cards,
                    'rejected_cards' => $shipBay->rejected_cards,
                    'penalty' => $shipBay->penalty,
                ],

                // Config data
                'config' => [
                    'baySize' => $baySize,
                    'bayCount' => $bayCount,
                    'bayTypes' => $bayTypes,
                ],

                // Container data
                // 'containers' => $containers,

                // Ship dock data
                'ship_dock' => $shipDock ? [
                    'arena' => $shipDock->arena,
                ] : null,

                // Restowage data
                'restowage' => [
                    'restowage_containers' => $restowageResponse['containers'] ?? [],
                    'restowage_moves' => $restowageResponse['moves'] ?? 0,
                    'restowage_penalty' => $restowageResponse['penalty'] ?? 0,
                ],

                // Bay capacity data
                'bay_capacity' => [
                    'is_full' => $isBayFull,
                ],

                // Unfulfilled containers data
                'unfulfilled_containers' => $unfulfilledContainers,

                'bay_statistics' => [
                    'bay_moves' => json_decode($shipBay->bay_moves, true) ?? [],
                    'dock_warehouse_containers' => $shipBay->dock_warehouse_containers,
                ],

                // Container destinations data
                'container_destinations' => $containerDestinations,
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch arena data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'arena' => 'required',
            'user_id' => 'required|exists:users,id',
            'room_id' => 'required|exists:rooms,id',
            'section' => 'sometimes|string|in:section1,section2',
            'moved_container' => 'sometimes|array',
            'moved_container.id' => 'sometimes|exists:containers,id',
            'moved_container.from' => 'sometimes|string',
            'moved_container.to' => 'sometimes|string',
            'move_type' => 'sometimes|string|in:discharge,load',
            'count' => 'sometimes|integer|min:1',
            'bay_index' => 'sometimes|integer|min:0',
            'container_id' => 'sometimes|exists:containers,id',
            'isLog' => 'sometimes|boolean',
        ]);

        $room = Room::find($validatedData['room_id']);

        $shipBay = ShipBay::where('user_id', $validatedData['user_id'])
            ->where('room_id', $validatedData['room_id'])
            ->first();

        if (!$shipBay) {
            if (!$room) {
                return response()->json(['message' => 'Room not found'], 404);
            }

            // Get user's port
            $userPort = ShipBay::where('user_id', $validatedData['user_id'])
                ->where('room_id', $validatedData['room_id'])
                ->value('port');

            // Create new ship bay
            $shipBay = new ShipBay();
            $shipBay->user_id = $validatedData['user_id'];
            $shipBay->room_id = $validatedData['room_id'];
            $shipBay->port = $userPort ?? '';
        }

        // Convert arena data to the new flat format
        $baySize = json_decode($room->bay_size, true);
        $arenaData = $this->convertArenaToStorageFormat(
            $validatedData['arena'],
            $room->bay_count,
            $baySize
        );

        $shipBay->arena = json_encode($arenaData);
        $shipBay->section = $validatedData['section'] ?? $shipBay->section;
        $shipBay->save();
        // $shipBay->revenue = $validatedData['revenue'] ?? $shipBay->revenue ?? 0;
        // $shipBay->total_revenue = ($shipBay->revenue ?? 0) - ($shipBay->penalty ?? 0);

        // Handle container movement tracking for revenue
        if (
            isset($validatedData['moved_container']) &&
            isset($validatedData['moved_container']['id']) &&
            isset($validatedData['moved_container']['from']) &&
            isset($validatedData['moved_container']['to'])
        ) {

            $container = Container::find($validatedData['moved_container']['id']);
            $fromLocation = $validatedData['moved_container']['from'];
            $toLocation = $validatedData['moved_container']['to'];

            // Check if moving from dock to bay or bay to dock
            $movingToBay = strpos($toLocation, 'bay-') === 0 && strpos($fromLocation, 'docks-') === 0;
            $movingToDock = strpos($toLocation, 'docks-') === 0 && strpos($fromLocation, 'bay-') === 0;

            if ($container && ($movingToBay || $movingToDock)) {
                $this->updateContainerFulfillmentStatus(
                    $container,
                    $validatedData['user_id'],
                    $validatedData['room_id'],
                    $movingToBay
                );
            }
        }

        if (isset($validatedData['isLog']) && $validatedData['isLog'] === true) {
            // Get the ship dock data
            $shipDock = ShipDock::where('user_id', $validatedData['user_id'])
                ->where('room_id', $validatedData['room_id'])
                ->first();

            if ($shipDock) {
                // Create simulation log
                SimulationLog::create([
                    'user_id' => $validatedData['user_id'],
                    'room_id' => $validatedData['room_id'],
                    'arena_bay' => $shipBay->arena,
                    'arena_dock' => $shipDock->arena,
                    'port' => $shipBay->port,
                    'section' => $shipBay->section,
                    'round' => $shipBay->current_round,
                    'revenue' => $shipBay->revenue ?? 0,
                    'penalty' => $shipBay->penalty ?? 0,
                    'total_revenue' => $shipBay->total_revenue,
                ]);
            }
        }

        // Calculate rankings
        $roomController = new RoomController();
        $rankings = $roomController->getUsersRanking($validatedData['room_id']);

        if (
            isset($validatedData['move_type']) &&
            isset($validatedData['count']) &&
            isset($validatedData['bay_index'])
        ) {
            // Use incrementMoves directly instead of creating a new function
            $moveTrackingRequest = new Request([
                'move_type' => $validatedData['move_type'],
                'count' => $validatedData['count'],
                'bay_index' => $validatedData['bay_index'],
                'container_id' => $validatedData['container_id'] ?? null,
                'arena' => $validatedData['arena'],
            ]);

            // Call incrementMoves method directly
            $moveResponse = $this->incrementMoves($moveTrackingRequest, $validatedData['room_id'], $validatedData['user_id']);

            // Add move tracking data to response
            return response()->json([
                'shipBay' => $shipBay,
                'moveTracking' => $moveResponse->original,
                'rankings' => $rankings->original,
            ], 200);
        }

        return response()->json([
            'shipBay' => $shipBay,
            'rankings' => $rankings->original,
        ], 200);
    }

    /**
     * Update container fulfillment status and possibly grant revenue
     */
    private function updateContainerFulfillmentStatus($container, $userId, $roomId, $isMovingToBay)
    {
        // Find active card temporary for this container
        $cardTemp = CardTemporary::where([
            'user_id' => $userId,
            'room_id' => $roomId,
            'card_id' => $container->card_id,
            'deck_id' => $container->deck_id,
            'status' => 'accepted',
        ])->first();

        if (!$cardTemp || !isset($cardTemp->unfulfilled_containers)) {
            return;
        }

        // Get the current round from ShipBay
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return;
        }

        $currentRound = $shipBay->current_round;
        $unfulfilled = $cardTemp->unfulfilled_containers ?? [];

        // Check if this is the original round that the card was shown in
        $isCardOriginalRound = ($cardTemp->round == $currentRound);

        if ($isMovingToBay) {
            // Container is being moved to ship bay - remove from unfulfilled list
            $unfulfilled = array_diff($unfulfilled, [$container->id]);
        } else {
            // Container is being moved back to dock

            // Only add back to unfulfilled list if we're in the same round as when
            // the card was presented (or if it's backlog from an earlier round)
            if ($isCardOriginalRound || ($cardTemp->is_backlog && $cardTemp->original_round < $currentRound)) {
                if (!in_array($container->id, $unfulfilled)) {
                    $unfulfilled[] = $container->id;
                }
            } else {
                // We're in a later round, don't add back to unfulfilled
                // (effectively preserving the revenue)
            }
        }

        $cardTemp->unfulfilled_containers = $unfulfilled;

        // If all containers are fulfilled and revenue not yet granted
        if (empty($unfulfilled) && !$cardTemp->revenue_granted) {
            $this->grantRevenueForCompletedCard($cardTemp, $roomId, $userId);
            $cardTemp->revenue_granted = true;
            $cardTemp->fulfillment_round = $currentRound; // Record when it was fulfilled
        }
        // Only revert revenue if we're in the card's original round
        elseif (!empty($unfulfilled) && $cardTemp->revenue_granted && $isCardOriginalRound) {
            $this->revertRevenueForCard($cardTemp, $roomId, $userId);
            $cardTemp->revenue_granted = false;
            $cardTemp->fulfillment_round = null; // Clear fulfillment round
        }

        $cardTemp->save();
    }

    /**
     * Grant revenue for a completed card
     */
    private function grantRevenueForCompletedCard($cardTemp, $roomId, $userId)
    {
        $card = Card::where('id', $cardTemp->card_id)
            ->where('deck_id', $cardTemp->deck_id)
            ->first();

        if (!$card) {
            return;
        }

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return;
        }

        // Add revenue from this card
        $cardRevenue = $card->revenue;
        $shipBay->revenue += $cardRevenue;
        $shipBay->total_revenue = $shipBay->revenue - $shipBay->penalty;
        $shipBay->save();
    }

    /**
     * Revert revenue if containers are moved back to dock
     */
    private function revertRevenueForCard($cardTemp, $roomId, $userId)
    {
        $card = Card::where('id', $cardTemp->card_id)
            ->where('deck_id', $cardTemp->deck_id)
            ->first();

        if (!$card) {
            return;
        }

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return;
        }

        // Subtract revenue from this card
        $cardRevenue = $card->revenue;
        $shipBay->revenue -= $cardRevenue;
        $shipBay->total_revenue = $shipBay->revenue - $shipBay->penalty;
        $shipBay->save();
    }

    /**
     * Convert bay arena data from 2D array to flat storage format
     */
    private function convertArenaToStorageFormat($arenaInput, $bayCount, $baySize)
    {
        // If already in our flat format, return as is
        if (isset($arenaInput['containers'])) {
            return $arenaInput;
        }

        // Convert 2D array to flat format
        $containers = [];
        $totalContainers = 0;
        $containerIds = [];

        // First pass: extract container IDs and basic positional data
        for ($bayIndex = 0; $bayIndex < $bayCount; $bayIndex++) {
            if (!isset($arenaInput[$bayIndex])) continue;

            $bayData = $arenaInput[$bayIndex];

            // Process each cell in the bay
            for ($rowIndex = 0; $rowIndex < $baySize['rows']; $rowIndex++) {
                for ($colIndex = 0; $colIndex < $baySize['columns']; $colIndex++) {
                    // Calculate position using the formula
                    $position = ($bayIndex * $baySize['rows'] * $baySize['columns']) + ($rowIndex * $baySize['columns']) + $colIndex;

                    // Get the container at this position
                    $container = $bayData[$rowIndex][$colIndex] ?? null;

                    if ($container) {
                        $containerIds[] = $container;
                        $containers[] = [
                            'id' => $container,
                            'position' => $position,
                            'bay' => $bayIndex,
                            'row' => $rowIndex,
                            'col' => $colIndex,
                            'type' => 'dry',
                            'cardId' => null,
                            'origin' => null,
                            'destination' => null
                        ];
                        $totalContainers++;
                    }
                }
            }
        }

        // If no containers found, return empty result
        if (empty($containerIds)) {
            return [
                'containers' => [],
                'totalContainers' => 0
            ];
        }

        $containerMetadata = DB::table('containers')
            ->select(
                'containers.id',
                'containers.type',
                'containers.card_id',
                'cards.id as card_id',
                'cards.origin',
                'cards.destination',
                'cards.type as card_type'
            )
            ->leftJoin('cards', function ($join) {
                $join->on('containers.card_id', '=', 'cards.id')
                    ->on('containers.deck_id', '=', 'cards.deck_id');
            })
            ->whereIn('containers.id', $containerIds)
            ->get()
            ->keyBy('id');

        // Enhance containers with metadata
        foreach ($containers as &$container) {
            $containerId = $container['id'];
            if (isset($containerMetadata[$containerId])) {
                $metadata = $containerMetadata[$containerId];

                // Add container type
                $container['type'] = $metadata->type ?? 'dry';

                // Add card related information
                $container['cardId'] = $metadata->card_id ?? null;
                $container['origin'] = $metadata->origin ?? null;
                $container['destination'] = $metadata->destination ?? null;
            }
        }

        return [
            'containers' => $containers,
            'totalContainers' => $totalContainers
        ];
    }

    // Add method to update section
    public function updateSection(Request $request, $roomId, $userId)
    {
        $validatedData = $request->validate([
            'section' => 'required|in:section1,section2',
        ]);

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        $room = Room::find($roomId);

        if (!$shipBay) {
            return response()->json(['error' => 'Ship bay not found'], 404);
        }

        // If trying to progress from section1 to section2, validate all containers are discharged
        if (($shipBay->section === 'section1' && $validatedData['section'] === 'section2')) {
            // Get the current port
            $currentPort = $shipBay->port;

            // Extract container IDs from the bay arena
            $containerIds = [];
            $arena = json_decode($shipBay->arena, true);

            if (isset($arena['containers']) && is_array($arena['containers'])) {
                // New flat format
                foreach ($arena['containers'] as $container) {
                    $containerIds[] = $container['id'];
                }
            } elseif (is_array($arena)) {
                // Legacy 2D array format
                foreach ($arena as $bay) {
                    if (is_array($bay)) {
                        foreach ($bay as $row) {
                            if (is_array($row)) {
                                foreach ($row as $cellContainerId) {
                                    if ($cellContainerId) {
                                        $containerIds[] = $cellContainerId;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (count($containerIds) > 0) {
                // Get containers and their destinations
                $containerData = Container::whereIn('id', $containerIds)
                    ->with(['card:id,destination'])
                    ->get();

                // Check if any container has the current port as destination
                $containersForCurrentPort = $containerData->filter(function ($container) use ($currentPort) {
                    return $container->card &&
                        $container->card->destination &&
                        strtoupper(trim($container->card->destination)) === strtoupper(trim($currentPort));
                });

                if ($containersForCurrentPort->count() > 0) {
                    return response()->json([
                        'error' => 'Please discharge all containers destined for your port first!',
                        'remaining_containers' => $containersForCurrentPort->count(),
                        'container_ids' => $containersForCurrentPort->pluck('id')->toArray()
                    ], 400);
                }
            }
        }
        // else if (($shipBay->current_round > $room->total_rounds)) {
        //     return response()->json([
        //         'error' => 'You cannot change section after the last round has ended.'
        //     ], 400);
        // }

        // If validation passes or not needed, update the section
        $shipBay->section = $validatedData['section'];
        $shipBay->save();

        return response()->json([
            'message' => 'Section updated successfully',
            'section' => $shipBay->section
        ], 200);
    }

    public function show($roomId, $userId)
    {
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        return response()->json($shipBay);
    }

    public function destroy(ShipBay $shipBay)
    {
        //
    }

    public function showBayByUserAndRoom($room, $user)
    {
        $shipBay = ShipBay::where('user_id', $user)
            ->where('room_id', $room)
            ->first();

        if (!$shipBay) {
            return response()->json(['message' => 'Ship bay not found'], 404);
        }

        $arenaData = json_decode($shipBay->arena, true);

        if (!isset($arenaData['containers'])) {
            $roomData = Room::find($room);
            $baySize = json_decode($roomData->bay_size, true);
            $arenaData = $this->convertArenaToStorageFormat(
                $arenaData,
                $roomData->bay_count,
                $baySize
            );

            $shipBay->arena = json_encode($arenaData);
            $shipBay->save();
        }

        $shipBay->arena = $arenaData;

        return response()->json($shipBay, 200);
    }

    public function incrementMoves(Request $request, $roomId, $userId)
    {
        $validatedData = $request->validate([
            'move_type' => 'required|in:discharge,load',
            'count' => 'required|integer|min:1',
            'bay_index' => 'required|integer|min:0',
            'container_id' => 'sometimes|exists:containers,id',
            'arena' => 'sometimes|array',
        ]);

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json(['message' => 'Ship bay not found'], 404);
        }

        $room = Room::find($roomId);
        $roomController = new RoomController();

        // ======= PHASE 1: CAPTURE COMPLETE PREVIOUS STATE =======
        $previousRestowageData = $roomController->calculateRestowagePenalties($room, $userId);
        $previousBayMoves = json_decode($shipBay->bay_moves ?? '{}', true);

        $previousContainerPositions = [];
        if (isset($previousArena['containers']) && is_array($previousArena['containers'])) {
            foreach ($previousArena['containers'] as $container) {
                $previousContainerPositions[$container['id']] = [
                    'position' => $container['position'],
                    'bay' => $container['bay'] ?? null,
                    'row' => $container['row'] ?? null,
                    'col' => $container['col'] ?? null
                ];
            }
        }

        $previousRestowageBays = [];
        $previousRestowageContainers = [];
        $previousBlockingContainers = [];

        if (isset($previousRestowageData['containers']) && is_array($previousRestowageData['containers'])) {
            foreach ($previousRestowageData['containers'] as $container) {
                if (isset($container['stack'])) {
                    $parts = explode('-', $container['stack']);
                    if (count($parts) >= 1) {
                        $bayIndex = (int)$parts[0];
                        $previousRestowageBays[$bayIndex] = true;
                    }
                }
                if (isset($container['container_id'])) {
                    $previousRestowageContainers[$container['container_id']] = $container;
                }
                if (isset($container['blocking_container_id'])) {
                    $previousBlockingContainers[$container['blocking_container_id']] = true;
                }
            }
        }

        // ======= PHASE 2: PROCESS THE MOVE =======
        $movingContainerId = $validatedData['container_id'] ?? null;
        $wasBlockingContainer = $movingContainerId && isset($previousBlockingContainers[$movingContainerId]);
        $containerPreviousPosition = $movingContainerId ? ($previousContainerPositions[$movingContainerId] ?? null) : null;

        $moveCost = $room->move_cost;
        $movesChargedForPenalty = $validatedData['count'];
        $freeLoadApplied = false;

        if ($validatedData['move_type'] === 'load' && isset($validatedData['container_id'])) {
            $container = Container::find($validatedData['container_id']);
            if ($container && $container->is_restowed) {
                $movesChargedForPenalty = max(0, $validatedData['count'] - 1);
                $freeLoadApplied = true;
                $container->is_restowed = false;
                $container->save();
            }
        }

        if (isset($validatedData['container_id'])) {
            $container = Container::find($validatedData['container_id']);
            if ($container) {
                $container->last_processed_by = $shipBay->port;
                $container->last_processed_at = now();
                $container->save();
            }
        }

        if ($validatedData['move_type'] === 'discharge') {
            $shipBay->discharge_moves += $validatedData['count'];
        } else {
            $shipBay->load_moves += $validatedData['count'];
        }

        $bayMoves = json_decode($shipBay->bay_moves ?? '{}', true);
        if (!isset($bayMoves[$validatedData['bay_index']])) {
            $bayMoves[$validatedData['bay_index']] = [
                'discharge_moves' => 0,
                'load_moves' => 0,
                'restowage_container_count' => 0,
                'restowage_moves' => 0
            ];
        }

        if ($validatedData['move_type'] === 'discharge') {
            $bayMoves[$validatedData['bay_index']]['discharge_moves'] += $validatedData['count'];
        } else {
            $bayMoves[$validatedData['bay_index']]['load_moves'] += $validatedData['count'];
        }

        // ======= PHASE 3: RELOAD ARENA TERBARU (PASTIKAN SUDAH SINKRON) =======
        $shipBay->save();
        $shipBay->refresh();
        $currentArena = json_decode($shipBay->arena, true);

        $currentContainerPositions = [];
        if (isset($currentArena['containers']) && is_array($currentArena['containers'])) {
            foreach ($currentArena['containers'] as $container) {
                $currentContainerPositions[$container['id']] = [
                    'position' => $container['position'],
                    'bay'      => $container['bay'] ?? null,
                    'row'      => $container['row'] ?? null,
                    'col'      => $container['col'] ?? null
                ];
            }
        }

        $movedContainers = [];
        foreach ($previousContainerPositions as $id => $prev) {
            if (isset($currentContainerPositions[$id])) {
                $curr = $currentContainerPositions[$id];
                if ($prev['position'] !== $curr['position'] || $prev['bay'] !== $curr['bay']) {
                    $movedContainers[$id] = ['from' => $prev, 'to' => $curr];
                }
            } else {
                $movedContainers[$id] = ['from' => $prev, 'to' => null];
            }
        }
        foreach ($currentContainerPositions as $id => $curr) {
            if (!isset($previousContainerPositions[$id])) {
                $movedContainers[$id] = ['from' => null, 'to' => $curr];
            }
        }

        // HITUNG RESTOWAGE BARU BERDASARKAN ARENA TERKINI =======
        $newRestowageData = $roomController->calculateRestowagePenalties($room, $userId);

        $currentRestowageBays = [];
        $currentBlockingContainers = [];
        if (isset($newRestowageData['containers']) && is_array($newRestowageData['containers'])) {
            foreach ($newRestowageData['containers'] as $container) {
                if (isset($container['stack'])) {
                    $parts = explode('-', $container['stack']);
                    if (count($parts) >= 1) {
                        $bayIndex = (int)$parts[0];
                        $currentRestowageBays[$bayIndex] = true;
                    }
                }
                if (isset($container['blocking_container_id'])) {
                    $currentBlockingContainers[$container['blocking_container_id']] = true;
                }
            }
        }

        $fixedBlockingContainer = $wasBlockingContainer && !isset($currentBlockingContainers[$movingContainerId]);

        // UPDATE BAY MOVES DENGAN RESTOWAGE DATA =======
        $restowageByBay = [];
        foreach ($newRestowageData['containers'] as $container) {
            if (isset($container['stack'])) {
                $parts = explode('-', $container['stack']);
                if (count($parts) >= 1) {
                    $bayIndex = (int)$parts[0];
                    if (!isset($restowageByBay[$bayIndex])) {
                        $restowageByBay[$bayIndex] = [
                            'restowage_container_count' => 0,
                            'restowage_moves' => 0,
                            'counted_containers' => []
                        ];
                    }
                    $blockingId = $container['blocking_container_id'];
                    if (!isset($restowageByBay[$bayIndex]['counted_containers'][$blockingId])) {
                        $restowageByBay[$bayIndex]['restowage_container_count']++;
                        $restowageByBay[$bayIndex]['restowage_moves'] += 2;
                        $restowageByBay[$bayIndex]['counted_containers'][$blockingId] = true;
                    }
                }
            }
        }

        foreach ($restowageByBay as $bayIndex => $data) {
            if (!isset($bayMoves[$bayIndex])) {
                $bayMoves[$bayIndex] = [
                    'discharge_moves' => 0,
                    'load_moves' => 0
                ];
            }
            $bayMoves[$bayIndex]['restowage_container_count'] = $data['restowage_container_count'];
            $bayMoves[$bayIndex]['restowage_moves'] = $data['restowage_moves'];
        }

        foreach ($bayMoves as $index => $moves) {
            $discharge = $moves['discharge_moves'] ?? 0;
            $load = $moves['load_moves'] ?? 0;
            $restowage = $moves['restowage_moves'] ?? 0;
            $bayMoves[$index]['total_moves'] = $discharge + $load + $restowage;
        }

        // ======= PHASE 7: UPDATE SHIPBAY DENGAN DATA BARU =======
        $movePenalty = $movesChargedForPenalty * $moveCost;
        $shipBay->bay_moves = json_encode($bayMoves);
        // $shipBay->restowage_containers = json_encode($newRestowageData['containers']);
        // $shipBay->restowage_moves = $newRestowageData['moves'];
        // $shipBay->restowage_penalty = $newRestowageData['penalty'];
        $shipBay->penalty += $movePenalty;
        $shipBay->total_revenue = $shipBay->revenue - $shipBay->penalty;
        $shipBay->save();

        $restowageImprovement = $previousRestowageData['moves'] > $newRestowageData['moves'];
        $restowageChangeAmount = $previousRestowageData['moves'] - $newRestowageData['moves'];

        $this->updateWeeklyPerformanceFromShipBay($shipBay);

        return response()->json([
            'message' => 'Moves incremented successfully',
            'free_load_applied' => $freeLoadApplied,
            'moves_charged' => $movesChargedForPenalty,
            'actual_moves' => $validatedData['count'],
            'penalty' => $movePenalty,
            'shipBay' => $shipBay,
            'restowage_fixed' => $fixedBlockingContainer,
            'restowage_improved' => $restowageImprovement,
            'restowage_improvement_amount' => $restowageChangeAmount > 0 ? $restowageChangeAmount : 0,
            'was_blocking_container' => $wasBlockingContainer,
            'previous_restowage_bays' => array_keys($previousRestowageBays),
            'current_restowage_bays' => array_keys($currentRestowageBays),
            'moved_containers' => $movedContainers
        ]);
    }

    private function updateWeeklyPerformanceFromShipBay($shipBay)
    {
        // Get current week
        $currentWeek = $shipBay->current_round;

        // Get WeeklyPerformance record
        $weeklyPerformance = WeeklyPerformance::firstOrCreate(
            [
                'room_id' => $shipBay->room_id,
                'user_id' => $shipBay->user_id,
                'week' => $currentWeek
            ]
        );

        // Get Room for cost rates
        $room = Room::find($shipBay->room_id);
        $moveCost = $room->move_cost;

        // Update move stats
        $weeklyPerformance->discharge_moves = $shipBay->discharge_moves;
        $weeklyPerformance->load_moves = $shipBay->load_moves;
        $weeklyPerformance->move_costs = ($shipBay->discharge_moves + $shipBay->load_moves) * $moveCost;

        // Update restowage data - FIX FOR THE ERROR
        // Check if restowage_containers is a JSON string and parse it
        $restowageContainers = null;
        if (is_string($shipBay->restowage_containers)) {
            $restowageContainers = json_decode($shipBay->restowage_containers, true);
        } elseif (is_array($shipBay->restowage_containers)) {
            $restowageContainers = $shipBay->restowage_containers;
        }

        // Count the containers (or default to 0 if null)
        $containerCount = 0;
        if (is_array($restowageContainers)) {
            $containerCount = count($restowageContainers);
        }

        // Store only the count in the integer field
        $weeklyPerformance->restowage_container_count = $containerCount;
        $weeklyPerformance->restowage_moves = $shipBay->restowage_moves;
        $weeklyPerformance->restowage_penalty = $shipBay->restowage_penalty;

        // Update financial stats
        $weeklyPerformance->revenue = $shipBay->revenue;
        $weeklyPerformance->total_penalty = $shipBay->penalty;
        $weeklyPerformance->dock_warehouse_penalty = $shipBay->dock_warehouse_penalty;
        $weeklyPerformance->unrolled_penalty = $shipBay->unrolled_penalty;
        $weeklyPerformance->net_result = $shipBay->total_revenue;

        // Update container counts (requires calculating from arena data)
        $arenaData = json_decode($shipBay->arena, true);
        $containerIds = $this->extractContainerIdsFromArena($arenaData);
        $containers = Container::whereIn('id', $containerIds)->get();

        $weeklyPerformance->dry_containers_loaded = $containers->where('type', 'dry')->count();
        $weeklyPerformance->reefer_containers_loaded = $containers->where('type', 'reefer')->count();

        // Save the changes
        $weeklyPerformance->save();
    }

    private function extractContainerIdsFromArena($arena)
    {
        $containerIds = [];

        if (isset($arena['containers']) && is_array($arena['containers'])) {
            foreach ($arena['containers'] as $container) {
                if (isset($container['id'])) {
                    $containerIds[] = $container['id'];
                }
            }
        }

        return $containerIds;
    }

    public function incrementCards(Request $request, $roomId, $userId)
    {
        $validatedData = $request->validate([
            'card_action' => 'required|string|in:accept,reject',
            'count' => 'required|integer|min:1',
            'card_temporary_id' => 'sometimes|required|exists:cards,id',
            'card' => 'sometimes|required|array',
            'port' => 'sometimes|required|string',
            'dock_arena' => 'sometimes|required|array',
            'dock_size' => 'sometimes|required|array',
        ]);

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        $room = Room::find($roomId);

        if ($validatedData['card_action'] === 'accept') {
            $shipBay->increment('accepted_cards', $validatedData['count']);
            $shipBay->increment('processed_cards', $validatedData['count']);
        } else {
            $shipBay->increment('rejected_cards', $validatedData['count']);
        }
        $shipBay->increment('current_round_cards');

        $totalProcessed = $shipBay->accepted_cards + $shipBay->rejected_cards;
        $isLimitExceeded = $totalProcessed >= $room->cards_limit_per_round;

        // Handle capacity uptake if data provided
        $capacityUptakeData = null;
        if ($request->has('card') && $request->has('port')) {
            $capacityUptakeController = new CapacityUptakeController();
            $capacityUptakeData = $capacityUptakeController->updateCapacityUptake($request, $roomId, $userId, $shipBay->current_round);
        }

        // Handle card temporary if ID provided
        $unfulfilledContainers = [];
        if ($request->has('card_temporary_id') && $request->card_action === 'accept') {
            $cardTemporaryController = new CardTemporaryController();
            $cardTempResult = $cardTemporaryController->acceptCardTemporary(new Request([
                'room_id' => $roomId,
                'card_temporary_id' => $request->card_temporary_id,
                'round' => $shipBay->current_round,
            ]));

            // Get unfulfilled containers
            $unfulfilledData = $cardTemporaryController->getUnfulfilledContainers($roomId, $userId);
            $unfulfilledContainers = $unfulfilledData->original;
        } else if ($request->has('card_temporary_id') && $request->card_action === 'reject') {
            $cardTemporaryController = new CardTemporaryController();
            $cardTemporaryController->rejectCardTemporary(new Request([
                'room_id' => $roomId,
                'card_temporary_id' => $request->card_temporary_id,
                'round' => $shipBay->current_round,
            ]));
        }

        $dockResponse = null;
        if ($request->has('dock_arena') && $request->has('dock_size')) {
            $shipDock = ShipDock::updateOrCreate(
                ['user_id' => $userId, 'room_id' => $roomId],
                [
                    'arena' => json_encode($request->dock_arena),
                    'dock_size' => json_encode($request->dock_size),
                ]
            );

            $dockResponse = [
                'message' => 'Ship dock updated successfully',
                'shipDock' => $shipDock
            ];
        }

        // After all operations are complete, create a simulation log
        if ($validatedData['card_action'] === 'accept') {
            SimulationLog::create([
                'user_id' => $userId,
                'room_id' => $roomId,
                'arena_bay' => $shipBay->arena,
                'arena_dock' => $shipDock->arena,
                'port' => $shipBay->port,
                'section' => $shipBay->section,
                'round' => $shipBay->current_round,
                'revenue' => $shipBay->revenue ?? 0,
                'penalty' => $shipBay->penalty ?? 0,
                'total_revenue' => $shipBay->total_revenue,
            ]);
        }

        return response()->json([
            'processed_cards' => $shipBay->processed_cards,
            'is_limit_exceeded' => $isLimitExceeded,
            'accepted_cards' => $shipBay->accepted_cards,
            'rejected_cards' => $shipBay->rejected_cards,
            'current_round_cards' => $shipBay->current_round_cards,
            'unfulfilled_containers' => $unfulfilledContainers,
            'capacity_uptake' => $capacityUptakeData ? $capacityUptakeData->original : null,
            'dock_data' => $dockResponse,
        ]);
    }

    public function getBayStatistics($roomId, $userId)
    {
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json(['message' => 'Ship bay not found'], 404);
        }

        $room = Room::find($roomId);

        // Get moves data
        $bayMoves = json_decode($shipBay->bay_moves ?? '{}', true);
        $totalMoves = $shipBay->discharge_moves + $shipBay->load_moves;
        $dockWarehouseContainers = $shipBay->dock_warehouse_containers;
        // $bayPairs = json_decode($shipBay->bay_pairs ?? '[]', true);
        // $idealCraneSplit = $room->ideal_crane_split ?? 2;
        // $longCraneMoves = $shipBay->long_crane_moves;
        // $extraMovesOnLongCrane = $shipBay->extra_moves_on_long_crane;

        return response()->json([
            'bay_moves' => $bayMoves,
            'total_moves' => $totalMoves,
            'dock_warehouse_containers' => $dockWarehouseContainers,
            // 'bay_pairs' => $bayPairs,
            // 'ideal_crane_split' => $idealCraneSplit,
            // 'ideal_average_moves_per_crane' => $totalMoves / $idealCraneSplit,
            // 'long_crane_moves' => $longCraneMoves,
            // 'extra_moves_on_long_crane' => $extraMovesOnLongCrane,
        ]);
    }

    public function getBayStatisticsHistory($roomId, $userId, $week = null)
    {
        try {
            $query = DB::table('bay_statistics_history')
                ->where('room_id', $roomId)
                ->where('user_id', $userId);

            if ($week) {
                $query->where('week', $week);
                $result = $query->first();

                if (!$result) {
                    return response()->json(['error' => 'No historical data found for week ' . $week], 404);
                }

                return response()->json($result);
            }

            return response()->json($query->orderBy('week')->get());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch historical statistics: ' . $e->getMessage()], 500);
        }
    }
}
