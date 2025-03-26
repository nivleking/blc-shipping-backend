<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\ShipBay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipBayController extends Controller
{
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'room_id' => 'required|exists:rooms,id',
            'arena' => 'required|array',
            'revenue' => 'required|numeric|min:0',
            'section' => 'sometimes|string|in:section1,section2',
        ]);

        $shipBay = ShipBay::where('user_id', $validatedData['user_id'])
            ->where('room_id', $validatedData['room_id'])
            ->first();

        if (!$shipBay) {
            $shipBay = new ShipBay();
            $shipBay->user_id = $validatedData['user_id'];
            $shipBay->room_id = $validatedData['room_id'];
        }

        $shipBay->arena = json_encode($validatedData['arena']);
        $shipBay->revenue = $validatedData['revenue'];
        $shipBay->section = $validatedData['section'] ?? 'section1';
        $shipBay->total_revenue = $validatedData['revenue'] - $shipBay->penalty; // Calculate total_revenue
        $shipBay->save();

        return response()->json($shipBay, 201);
    }

    // Add method to update section
    public function updateSection(Request $request, $roomId, $userId)
    {
        $validatedData = $request->validate([
            'section' => 'required|string|in:section1,section2'
        ]);

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json(['message' => 'Ship bay not found'], 404);
        }

        $shipBay->update(['section' => $validatedData['section']]);

        return response()->json($shipBay);
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

        return response()->json($shipBay, 200);
    }

    public function incrementMoves(Request $request, $roomId, $userId)
    {
        $validatedData = $request->validate([
            'move_type' => 'required|in:discharge,load',
            'count' => 'required|integer|min:1',
            'bay_index' => 'required|integer|min:0'
        ]);

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json(['message' => 'Ship bay not found'], 404);
        }

        $room = Room::find($roomId);
        $moveCost = $room->move_cost;

        // Calculate direct move penalty
        $movePenalty = $validatedData['count'] * $moveCost;

        // Increment move counter
        if ($validatedData['move_type'] === 'discharge') {
            $shipBay->discharge_moves += $validatedData['count'];
        } else {
            $shipBay->load_moves += $validatedData['count'];
        }

        // Also track bay-specific moves
        $bayMoves = json_decode($shipBay->bay_moves ?? '{}', true);
        if (!isset($bayMoves[$validatedData['bay_index']])) {
            $bayMoves[$validatedData['bay_index']] = [
                'discharge_moves' => 0,
                'load_moves' => 0
            ];
        }

        if ($validatedData['move_type'] === 'discharge') {
            $bayMoves[$validatedData['bay_index']]['discharge_moves'] += $validatedData['count'];
        } else {
            $bayMoves[$validatedData['bay_index']]['load_moves'] += $validatedData['count'];
        }

        // Calculate total moves per bay
        foreach ($bayMoves as $index => $moves) {
            $bayMoves[$index]['total_moves'] =
                ($moves['discharge_moves'] ?? 0) + ($moves['load_moves'] ?? 0);
        }

        // Update bay moves in ship bay
        $shipBay->bay_moves = json_encode($bayMoves);

        // Calculate bay pairs and extra moves
        $bayCount = $room->bay_count;
        $totalMoves = $shipBay->discharge_moves + $shipBay->load_moves;
        $idealCraneSplit = $room->ideal_crane_split ?? 2;
        $idealMovesPerCrane = $idealCraneSplit > 0 ? $totalMoves / $idealCraneSplit : 0;

        // Create bay pairs
        $bayPairs = [];

        // First bay is its own pair
        $bayPairs[] = [
            'bays' => [0],
            'total_moves' => $bayMoves[0]['total_moves'] ?? 0
        ];

        // Process remaining bays in pairs
        for ($i = 1; $i < $bayCount; $i += 2) {
            if ($i + 1 < $bayCount) {
                // Regular pair
                $bayPairs[] = [
                    'bays' => [$i, $i + 1],
                    'total_moves' => ($bayMoves[$i]['total_moves'] ?? 0) +
                        ($bayMoves[$i + 1]['total_moves'] ?? 0)
                ];
            } else {
                // Last bay forms its own pair
                $bayPairs[] = [
                    'bays' => [$i],
                    'total_moves' => $bayMoves[$i]['total_moves'] ?? 0
                ];
            }
        }

        // Find the bay pair with the most moves (long crane)
        $longCraneMoves = 0;
        foreach ($bayPairs as $pair) {
            if ($pair['total_moves'] > $longCraneMoves) {
                $longCraneMoves = $pair['total_moves'];
            }
        }

        // Calculate extra moves on long crane
        $extraMovesOnLongCrane = max(0, $longCraneMoves - $idealMovesPerCrane);
        $extraMovesPenalty = round($extraMovesOnLongCrane) * $room->extra_moves_cost;

        // Update ship bay with all calculated values
        $shipBay->bay_pairs = json_encode($bayPairs);
        $shipBay->long_crane_moves = $longCraneMoves;
        $shipBay->extra_moves_on_long_crane = round($extraMovesOnLongCrane);

        // Update penalties
        $shipBay->penalty += $movePenalty; // Add the new move penalty
        $shipBay->extra_moves_penalty = $extraMovesPenalty; // Replace with current calculation

        // Calculate final revenue
        $shipBay->total_revenue = $shipBay->revenue - $shipBay->penalty - $shipBay->extra_moves_penalty;

        $shipBay->save();

        return response()->json($shipBay);
    }

    private function recalculateBayPairs($shipBay)
    {
        // Similar logic to calculateBayPairs but works on the shipBay object directly
        $room = Room::find($shipBay->room_id);
        $bayCount = $room->bay_count;

        $bayMoves = json_decode($shipBay->bay_moves ?? '{}', true);

        // Initialize bay statistics for any missing bays
        for ($i = 0; $i < $bayCount; $i++) {
            if (!isset($bayMoves[$i])) {
                $bayMoves[$i] = [
                    'discharge_moves' => 0,
                    'load_moves' => 0
                ];
            }

            // Calculate total moves per bay
            $bayMoves[$i]['total_moves'] =
                $bayMoves[$i]['discharge_moves'] + $bayMoves[$i]['load_moves'];
        }

        // Create bay pairs
        $bayPairs = [];

        // First bay is its own pair
        $bayPairs[] = [
            'bays' => [0],
            'total_moves' => $bayMoves[0]['total_moves'] ?? 0
        ];

        // Process remaining bays in pairs
        for ($i = 1; $i < $bayCount; $i += 2) {
            if ($i + 1 < $bayCount) {
                // Regular pair
                $bayPairs[] = [
                    'bays' => [$i, $i + 1],
                    'total_moves' => ($bayMoves[$i]['total_moves'] ?? 0) +
                        ($bayMoves[$i + 1]['total_moves'] ?? 0)
                ];
            } else {
                // Last bay forms its own pair if odd number of bays
                $bayPairs[] = [
                    'bays' => [$i],
                    'total_moves' => $bayMoves[$i]['total_moves'] ?? 0
                ];
            }
        }

        // Find the bay pair with the most moves (long crane)
        $longCraneMoves = 0;
        foreach ($bayPairs as $pair) {
            if ($pair['total_moves'] > $longCraneMoves) {
                $longCraneMoves = $pair['total_moves'];
            }
        }

        // Calculate total moves across all bays
        $totalMoves = 0;
        foreach ($bayMoves as $bayMove) {
            $totalMoves += $bayMove['total_moves'] ?? 0;
        }

        // Calculate ideal average moves per crane
        $idealCraneSplit = $room->ideal_crane_split ?? 2;
        $idealAverageMovesPerCrane = $idealCraneSplit > 0 ? $totalMoves / $idealCraneSplit : 0;

        // Calculate extra moves on long crane
        $extraMovesOnLongCrane = max(0, $longCraneMoves - $idealAverageMovesPerCrane);

        // Update the ship bay with the new calculations
        $shipBay->bay_pairs = json_encode($bayPairs);
        $shipBay->bay_moves = json_encode($bayMoves);
        $shipBay->long_crane_moves = $longCraneMoves;
        $shipBay->extra_moves_on_long_crane = round($extraMovesOnLongCrane);
        $shipBay->save();
    }

    public function incrementCards(Request $request, $roomId, $userId)
    {
        $validatedData = $request->validate([
            'card_action' => 'required|in:accept,reject',
            'count' => 'required|integer|min:1'
        ]);

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json(['message' => 'Ship bay not found'], 404);
        }

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

        return response()->json([
            'processed_cards' => $shipBay->processed_cards,
            'current_round_cards' => $shipBay->current_round_cards,
            'accepted_cards' => $shipBay->accepted_cards,
            'rejected_cards' => $shipBay->rejected_cards,
            'must_process_complete' => $shipBay->processed_cards >= $room->cards_must_process_per_round,
            'limit_exceeded' => $isLimitExceeded,
            'remaining_cards' => max(0, $room->cards_limit_per_round - $totalProcessed)
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
        $bayCount = $room->bay_count;

        // Get moves data
        $bayMoves = json_decode($shipBay->bay_moves ?? '{}', true);
        $bayPairs = json_decode($shipBay->bay_pairs ?? '[]', true);
        $totalMoves = $shipBay->discharge_moves + $shipBay->load_moves;
        $idealCraneSplit = $room->ideal_crane_split ?? 2;
        $longCraneMoves = $shipBay->long_crane_moves;
        $extraMovesOnLongCrane = $shipBay->extra_moves_on_long_crane;

        return response()->json([
            'bay_moves' => $bayMoves,
            'bay_pairs' => $bayPairs,
            'total_moves' => $totalMoves,
            'ideal_crane_split' => $idealCraneSplit,
            'ideal_average_moves_per_crane' => $totalMoves / $idealCraneSplit,
            'long_crane_moves' => $longCraneMoves,
            'extra_moves_on_long_crane' => $extraMovesOnLongCrane
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
