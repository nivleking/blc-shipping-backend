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

        $shipBay = ShipBay::updateOrCreate(
            [
                'user_id' => $validatedData['user_id'],
                'room_id' => $validatedData['room_id']
            ],
            [
                'arena' => json_encode($validatedData['arena']),
                'revenue' => $validatedData['revenue'],
                'section' => $validatedData['section'] ?? 'section1', // Set section
            ]
        );

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


}
