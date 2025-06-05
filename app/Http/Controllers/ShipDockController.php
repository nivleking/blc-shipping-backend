<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\ShipBay;
use App\Models\ShipDock;
use App\Models\SimulationLog;
use Illuminate\Http\Request;

class ShipDockController extends Controller
{
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
            'dock_size' => 'required|array',
        ]);

        // Convert arena from frontend to storage format if needed
        $arenaData = $this->convertArenaToStorageFormat($validatedData['arena']);

        $shipDock = ShipDock::updateOrCreate(
            ['user_id' => $validatedData['user_id'], 'room_id' => $validatedData['room_id']],
            [
                'arena' => json_encode($arenaData),
                'dock_size' => json_encode($validatedData['dock_size']),
            ]
        );

        $this->logSimulationState($validatedData['user_id'], $validatedData['room_id'], $shipDock);

        return response()->json(['message' => 'Ship dock saved successfully', 'shipDock' => $shipDock], 200);
    }

    /**
     * Log simulation state after ship dock update
     */
    private function logSimulationState($userId, $roomId, $shipDock)
    {
        $shipBay = ShipBay::where('user_id', $userId)
            ->where('room_id', $roomId)
            ->first();

        if ($shipBay) {
            $logData = [
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
            ];

            SimulationLog::create($logData);
        }
    }

    /**
     * Convert arena data from frontend format to storage format
     */
    private function convertArenaToStorageFormat($arenaInput)
    {
        // If already in our flat format, return as is
        if (isset($arenaInput['containers'])) {
            return $arenaInput;
        }

        // If it's a 2D array, convert to our flat format
        if (is_array($arenaInput)) {
            $containers = [];
            $position = 0;

            // If it's a flat object with dock-X keys, convert to our format
            if (is_array($arenaInput) && !isset($arenaInput[0])) {
                foreach ($arenaInput as $key => $containerId) {
                    if (strpos($key, 'docks-') === 0 && $containerId) {
                        $position = (int)str_replace('docks-', '', $key);
                        $containers[] = [
                            'id' => $containerId,
                            'position' => $position
                        ];
                    }
                }
            } else {
                // It's a 2D array, flatten it
                foreach ($arenaInput as $row) {
                    foreach ($row as $containerId) {
                        if ($containerId) {
                            $containers[] = [
                                'id' => $containerId,
                                'position' => $position
                            ];
                        }
                        $position++;
                    }
                }
            }

            return [
                'containers' => $containers,
                'totalContainers' => count($containers)
            ];
        }

        // If it's already JSON string, parse and check
        $decoded = json_decode($arenaInput, true);
        if (isset($decoded['containers'])) {
            return $decoded;
        }

        // Default to empty structure
        return [
            'containers' => [],
            'totalContainers' => 0
        ];
    }

    public function showDockByUserAndRoom($room, $user)
    {
        $shipDock = ShipDock::where('user_id', $user)
            ->where('room_id', $room)
            ->first();

        if (!$shipDock) {
            return response()->json(['message' => 'Ship dock not found'], 404);
        }

        // When using model's array casting, we need to handle arena data properly
        // The arena is already an array due to the cast in the model
        $arenaData = $shipDock->arena;

        // Ensure we have the containers array
        if (isset($arenaData['containers']) && !empty($arenaData['containers'])) {
            // Get all container IDs from the arena
            $containerIds = array_column($arenaData['containers'], 'id');

            // Fetch container metadata from the database
            $containerModels = Container::whereIn('id', $containerIds)->get()->keyBy('id');

            // Enrich arena data with metadata from Container models
            foreach ($arenaData['containers'] as &$container) {
                if (isset($containerModels[$container['id']])) {
                    $dbContainer = $containerModels[$container['id']];
                    // Add the is_restowed flag from the database
                    $container['is_restowed'] = $dbContainer->is_restowed;
                }
            }
        }

        // Build response with the arena data directly (not as a string)
        $response = $shipDock->toArray();

        // Override arena with our enriched version
        $response['arena'] = $arenaData;

        // Calculate total containers count
        $response['total_containers'] = isset($arenaData['totalContainers'])
            ? $arenaData['totalContainers']
            : count($arenaData['containers'] ?? []);

        return response()->json($response, 200);
    }
}
