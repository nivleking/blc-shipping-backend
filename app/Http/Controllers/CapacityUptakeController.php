<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCapacityUptakeRequest;
use App\Http\Requests\UpdateCapacityUptakeRequest;
use App\Models\CapacityUptake;
use App\Models\Container;
use App\Models\Room;
use App\Models\ShipBay;
use Illuminate\Http\Request;

class CapacityUptakeController extends Controller
{
    /**
     * Display capacity uptake data
     */
    public function getCapacityUptake($roomId, $userId, $week = null)
    {
        if ($week) {
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
        $swapConfig = $room ? json_decode($room->swap_config, true) : [];

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

        // Asegurarse de que arena_end también esté decodificado si existe
        if (is_string($responseData['arena_end'])) {
            $responseData['arena_end'] = json_decode($responseData['arena_end'], true);
        }
        $capacityUptake->swap_config = $swapConfig;

        return response()->json([
            'message' => 'Capacity uptake data retrieved successfully',
            'data' => $responseData,
        ], 200);
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
            $card['processed_at'] = now()->timestamp;
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

        return [
            'user_id' => $userId,
            'room_id' => $roomId,
            'week' => $week,
            'port' => $port,
            'swap_config' => $swapConfig,
            'accepted_cards' => [],
            'rejected_cards' => [],
            'arena_start' => [],
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
