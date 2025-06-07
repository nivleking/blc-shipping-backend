<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCapacityUptakeRequest;
use App\Http\Requests\UpdateCapacityUptakeRequest;
use App\Models\CapacityUptake;
use App\Models\Card;
use App\Models\CardTemporary;
use App\Models\Container;
use App\Models\Room;
use App\Models\ShipBay;
use App\Utilities\RedisService;
use Illuminate\Http\Request;

class CapacityUptakeController extends Controller
{

    protected $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    /**
     * Display capacity uptake data
     */
    public function getCapacityUptake($roomId, $userId, $week = null)
    {
        $useCache = request()->query('useCache');
        if ($useCache !== null) {
            $useCache = filter_var($useCache, FILTER_VALIDATE_BOOLEAN);
        } else {
            $useCache = false;
        }

        if ($week) {
            // Generate a cache key for this specific request
            $cacheKey = $this->redisService->generateKey('capacity_uptake', [
                'room' => $roomId,
                'user' => $userId,
                'week' => $week
            ]);

            // Try to get from cache if enabled
            if ($useCache && $this->redisService->has($cacheKey)) {
                return response()->json([
                    'data' => $this->redisService->get($cacheKey, null, true),
                    'source' => 'redis'
                ], 200);
            }

            $capacityUptake = CapacityUptake::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->where('week', $week)
                ->first();
        } else {
            $capacityUptake = CapacityUptake::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->orderBy('week', 'desc')
                ->first();
        }

        if (!$capacityUptake) {
            return response()->json([
                'data' => $this->generateDefaultCapacityData($roomId, $userId, $week)
            ], 200);
        }
        // Get the room to access swap_config
        $room = Room::find($roomId);
        $swapConfig = [];
        if ($room) {
            if (is_string($room->swap_config)) {
                $decoded = json_decode($room->swap_config, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $swapConfig = $decoded;
                }
            } elseif (is_array($room->swap_config)) {
                $swapConfig = $room->swap_config;
            }
        }

        // Get the ship bay to access arena
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        // Get the current port
        $currentPort = $capacityUptake->port;

        // Calculate maximum capacity from bay configuration
        $baySize = $room ? json_decode($room->bay_size, true) : ['rows' => 0, 'columns' => 0];
        $bayCount = $room ? $room->bay_count : 0;
        $bayTypes = $room ? json_decode($room->bay_types, true) : [];

        // Count reefer bays
        $reeferBayCount = 0;
        if ($bayTypes) {
            foreach ($bayTypes as $type) {
                if ($type === 'reefer') {
                    $reeferBayCount++;
                }
            }
        }

        // Calculate capacities
        $totalBayCells = $baySize['rows'] * $baySize['columns'] * $bayCount;
        $reeferCapacity = $reeferBayCount * $baySize['rows'] * $baySize['columns'];
        $dryCapacity = $totalBayCells - $reeferCapacity;

        // Enhance response with additional data
        $responseData = $capacityUptake->toArray();
        $responseData['swap_config'] = $swapConfig;
        $responseData['max_capacity'] = [
            'dry' => $dryCapacity,
            'reefer' => $reeferCapacity,
            'total' => $totalBayCells
        ];

        // Si arena_start es una cadena JSON, decodifícala primero
        if (is_string($responseData['arena_start'])) {
            $responseData['arena_start'] = json_decode($responseData['arena_start'], true);
        }

        // Procesar arena_start desde ShipBay si está vacío o nulo
        if (empty($responseData['arena_start']) && $shipBay) {
            $arenaData = json_decode($shipBay->arena, true);
            if (isset($arenaData['containers']) && !empty($arenaData['containers'])) {
                $containerIds = array_column($arenaData['containers'], 'id');

                // Obtener contenedores con información de destino
                $containers = Container::whereIn('id', $containerIds)
                    ->get()
                    ->keyBy('id');

                // Recopilar todos los card_id de los contenedores
                $cardIds = $containers->pluck('card_id')->filter()->toArray();

                // Consulta tarjetas directamente
                $cards = [];
                if (!empty($cardIds)) {
                    $cards = \App\Models\Card::whereIn('id', $cardIds)
                        ->select('id', 'destination', 'type')
                        ->get()
                        ->keyBy('id');
                }

                // Añadir destino y tipo de las tarjetas a los contenedores
                foreach ($arenaData['containers'] as &$container) {
                    if (isset($containers[$container['id']])) {
                        $dbContainer = $containers[$container['id']];
                        $container['type'] = $dbContainer->type;

                        // Añadir destino desde la tarjeta
                        if ($dbContainer->card_id && isset($cards[$dbContainer->card_id])) {
                            $card = $cards[$dbContainer->card_id];
                            $container['destination'] = $card->destination;
                        } else {
                            $container['destination'] = null;
                        }
                    }
                }

                $responseData['arena_start'] = $arenaData;
            }
        }

        // Calculate backlog containers from previous weeks
        $backlogData = $this->calculateBacklogContainers($roomId, $userId, $week);
        $responseData['backlog'] = $backlogData;

        // Asegurarse de que arena_end también esté decodificado si existe
        if (is_string($responseData['arena_end'])) {
            $responseData['arena_end'] = json_decode($responseData['arena_end'], true);
        }
        $capacityUptake->swap_config = $swapConfig;

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

        if ($useCache) {
            $this->redisService->set($cacheKey, $responseData, 3600, true);
        }

        return response()->json([
            'message' => 'Capacity uptake data retrieved successfully',
            'data' => $responseData,
            'unfulfilled_containers' => $unfulfilledContainers,
            'source' => 'database'
        ], 200);
    }

    /**
     * Calculate backlog containers (cards with is_backlog=true)
     */
    private function calculateBacklogContainers($roomId, $userId, $week)
    {
        // Default backlog structure
        $backlog = [
            'dry' => 0,
            'reefer' => 0,
            'total' => 0,
            'cards' => []
        ];

        // Get all backlogged card temporaries for this user and room
        $backloggedCards = CardTemporary::where([
            'room_id' => $roomId,
            'user_id' => $userId,
            'is_backlog' => true,
        ])
            ->where(function ($query) use ($week) {
                // Cards from current week that are backlogged, or backlogged in any week if week isn't specified
                if ($week) {
                    $query->where('round', $week);
                }
            })
            ->get();

        if ($backloggedCards->isEmpty()) {
            return $backlog;
        }

        // Get all card IDs to fetch card details
        $cardIds = $backloggedCards->pluck('card_id')->toArray();
        $deckIds = $backloggedCards->pluck('deck_id')->toArray();

        // Combine them to create card identification pairs
        $cardPairs = [];
        foreach ($backloggedCards as $temp) {
            $cardPairs[] = [
                'card_id' => $temp->card_id,
                'deck_id' => $temp->deck_id
            ];
        }

        // Fetch all corresponding cards with their details
        $cards = [];
        foreach ($cardPairs as $pair) {
            $card = Card::where('id', $pair['card_id'])
                ->where('deck_id', $pair['deck_id'])
                ->first();

            if ($card) {
                $cards[] = $card;
                // Count containers by type
                if ($card->type === 'dry') {
                    $backlog['dry'] += $card->quantity;
                } else if ($card->type === 'reefer') {
                    $backlog['reefer'] += $card->quantity;
                }
                // Add card data to backlog info
                $backlog['cards'][] = [
                    'id' => $card->id,
                    'type' => $card->type,
                    'priority' => $card->priority,
                    'quantity' => $card->quantity,
                    'origin' => $card->origin,
                    'destination' => $card->destination,
                    'revenue' => $card->revenue,
                    'original_round' => $backloggedCards->where('card_id', $card->id)->first()->original_round
                ];
            }
        }

        // Calculate total backlogged containers
        $backlog['total'] = $backlog['dry'] + $backlog['reefer'];

        return $backlog;
    }

    /**
     * Update capacity uptake with card info
     */
    public function updateCapacityUptake(Request $request, $roomId, $userId, $week)
    {
        $request->validate([
            'card_action' => 'required|string|in:accept,reject',
            'card' => 'required|array',
            'port' => 'required|string',
        ]);

        $capacityUptake = CapacityUptake::firstOrNew([
            'room_id' => $roomId,
            'user_id' => $userId,
            'week' => $week,
            'port' => $request->port,
        ]);

        $card = $request->card;
        $isCommitted = strtolower($card['priority']) === 'committed';
        $isDry = strtolower($card['type']) === 'dry';
        $quantity = $card['quantity'] ?? 1;

        if ($request->card_action === 'accept') {
            $acceptedCards = $capacityUptake->accepted_cards ?? [];
            $card['handled_at'] = now()->timestamp;
            $acceptedCards[] = $card;
            $capacityUptake->accepted_cards = $acceptedCards;

            if ($isDry) {
                $capacityUptake->dry_containers_accepted = ($capacityUptake->dry_containers_accepted ?? 0) + $quantity;
            } else {
                $capacityUptake->reefer_containers_accepted = ($capacityUptake->reefer_containers_accepted ?? 0) + $quantity;
            }

            if ($isCommitted) {
                $capacityUptake->committed_containers_accepted = ($capacityUptake->committed_containers_accepted ?? 0) + $quantity;
            } else {
                $capacityUptake->non_committed_containers_accepted = ($capacityUptake->non_committed_containers_accepted ?? 0) + $quantity;
            }
        } else {
            $rejectedCards = $capacityUptake->rejected_cards ?? [];
            $card['handled_at'] = now()->timestamp;
            $rejectedCards[] = $card;
            $capacityUptake->rejected_cards = $rejectedCards;

            if ($isDry) {
                $capacityUptake->dry_containers_rejected = ($capacityUptake->dry_containers_rejected ?? 0) + $quantity;
            } else {
                $capacityUptake->reefer_containers_rejected = ($capacityUptake->reefer_containers_rejected ?? 0) + $quantity;
            }

            if ($isCommitted) {
                $capacityUptake->committed_containers_rejected = ($capacityUptake->committed_containers_rejected ?? 0) + $quantity;
            } else {
                $capacityUptake->non_committed_containers_rejected = ($capacityUptake->non_committed_containers_rejected ?? 0) + $quantity;
            }
        }

        $capacityUptake->save();

        return response()->json([
            'message' => 'Capacity uptake data updated successfully',
            'data' => $capacityUptake
        ], 200);
    }

    /**
     * Generate default capacity data
     */
    private function generateDefaultCapacityData($roomId, $userId, $week = null)
    {
        // Get current week if not specified
        if (!$week) {
            $shipBay = ShipBay::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->first();
            $week = $shipBay ? $shipBay->current_round : 1;
        }

        // Get port
        $port = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->value('port') ?? '';

        // Get room for swap_config and bay info
        $room = Room::find($roomId);
        $swapConfig = $room ? json_decode($room->swap_config, true) : [];

        // Calculate maximum capacity from bay configuration
        $baySize = $room ? json_decode($room->bay_size, true) : ['rows' => 0, 'columns' => 0];
        $bayCount = $room ? $room->bay_count : 0;
        $bayTypes = $room ? json_decode($room->bay_types, true) : [];

        // Count reefer bays
        $reeferBayCount = 0;
        if ($bayTypes) {
            foreach ($bayTypes as $type) {
                if ($type === 'reefer') {
                    $reeferBayCount++;
                }
            }
        }

        // Calculate capacities
        $totalBayCells = $baySize['rows'] * $baySize['columns'] * $bayCount;
        $reeferCapacity = $reeferBayCount * $baySize['rows'] * $baySize['columns'];
        $dryCapacity = $totalBayCells - $reeferCapacity;

        // Calculate backlog containers
        $backlog = $this->calculateBacklogContainers($roomId, $userId, $week);

        return [
            'user_id' => $userId,
            'room_id' => $roomId,
            'week' => $week,
            'port' => $port,
            'swap_config' => $swapConfig,
            'accepted_cards' => [],
            'rejected_cards' => [],
            'arena_start' => [],
            'backlog' => $backlog,
            'dry_containers_accepted' => 0,
            'reefer_containers_accepted' => 0,
            'committed_containers_accepted' => 0,
            'non_committed_containers_accepted' => 0,
            'dry_containers_rejected' => 0,
            'reefer_containers_rejected' => 0,
            'committed_containers_rejected' => 0,
            'non_committed_containers_rejected' => 0,
            'max_capacity' => [
                'dry' => $dryCapacity,
                'reefer' => $reeferCapacity,
                'total' => $totalBayCells
            ],
        ];
    }
}
