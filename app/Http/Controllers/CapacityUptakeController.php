<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCapacityUptakeRequest;
use App\Http\Requests\UpdateCapacityUptakeRequest;
use App\Models\CapacityUptake;
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

        return response()->json([
            'message' => 'Capacity uptake data retrieved successfully',
            'data' => $capacityUptake
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

        return [
            'user_id' => $userId,
            'room_id' => $roomId,
            'week' => $week,
            'port' => $port,
            'accepted_cards' => [],
            'rejected_cards' => [],
            'dry_containers_accepted' => 0,
            'reefer_containers_accepted' => 0,
            'committed_containers_accepted' => 0,
            'non_committed_containers_accepted' => 0,
            'dry_containers_rejected' => 0,
            'reefer_containers_rejected' => 0,
            'committed_containers_rejected' => 0,
            'non_committed_containers_rejected' => 0,
        ];
    }
}
