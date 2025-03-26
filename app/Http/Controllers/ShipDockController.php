<?php

namespace App\Http\Controllers;

use App\Models\ShipDock;
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

        return response()->json(['message' => 'Ship dock saved successfully', 'shipDock' => $shipDock], 201);
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

        // Parse the arena JSON
        $arenaData = json_decode($shipDock->arena, true);

        // Add pagination metadata for frontend
        $response = $shipDock->toArray();
        $response['total_containers'] = isset($arenaData['totalContainers'])
            ? $arenaData['totalContainers']
            : count($arenaData['containers'] ?? []);

        return response()->json($response, 200);
    }
}
