<?php

namespace App\Http\Controllers;

use App\Models\ShipBay;
use Illuminate\Http\Request;

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

    // Add new methods
    public function incrementMoves(Request $request, $roomId, $userId)
    {
        $validatedData = $request->validate([
            'move_type' => 'required|in:discharge,load',
            'count' => 'required|integer|min:1'
        ]);

        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json(['message' => 'Ship bay not found'], 404);
        }

        $moveCost = 1000000;
        $penalty = $validatedData['count'] * $moveCost;
        $shipBay->increment('penalty', $penalty);

        // Update total_revenue
        $shipBay->total_revenue = $shipBay->revenue - $shipBay->penalty;

        // Increment move counter
        if ($validatedData['move_type'] === 'discharge') {
            $shipBay->increment('discharge_moves', $validatedData['count']);
        } else {
            $shipBay->increment('load_moves', $validatedData['count']);
        }

        $shipBay->save();

        return response()->json($shipBay);
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

        if ($validatedData['card_action'] === 'accept') {
            $shipBay->increment('accepted_cards', $validatedData['count']);
        } else {
            $shipBay->increment('rejected_cards', $validatedData['count']);
        }

        $shipBay->current_round_cards = $shipBay->current_round_cards + 1;
        $shipBay->save();

        return response()->json(['current_round_cards' => $shipBay->current_round_cards]);
    }
}
