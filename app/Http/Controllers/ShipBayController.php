<?php

namespace App\Http\Controllers;

use App\Models\ShipBay;
use Illuminate\Http\Request;

class ShipBayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'arena' => 'required|array',
            'user_id' => 'required|exists:users,id',
            'room_id' => 'required|exists:rooms,id',
        ]);

        $shipBay = ShipBay::updateOrCreate(
            ['user_id' => $validatedData['user_id'], 'room_id' => $validatedData['room_id']],
            ['arena' => json_encode($validatedData['arena'])]
        );

        return response()->json(['message' => 'Ship bay saved successfully', 'shipBay' => $shipBay], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ShipBay $shipBay)
    {
        return response()->json($shipBay, 200);
    }

    public function showByUserAndRoom($room, $user)
    {
        $shipBay = ShipBay::where('user_id', $user)
            ->where('room_id', $room)
            ->first();

        if (!$shipBay) {
            return response()->json(['message' => 'Ship bay not found'], 404);
        }

        return response()->json($shipBay, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ShipBay $shipBay)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ShipBay $shipBay)
    {
        //
    }
}
