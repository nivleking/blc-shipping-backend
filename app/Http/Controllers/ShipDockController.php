<?php

namespace App\Http\Controllers;

use App\Models\ShipDock;
use Illuminate\Http\Request;

class ShipDockController extends Controller
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
            'dock_size' => 'required|array',
        ]);

        $shipDock = ShipDock::updateOrCreate(
            ['user_id' => $validatedData['user_id'], 'room_id' => $validatedData['room_id']],
            [
                'arena' => json_encode($validatedData['arena']),
                'dock_size' => json_encode($validatedData['dock_size']),
            ]
        );

        return response()->json(['message' => 'Ship dock saved successfully', 'shipDock' => $shipDock], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ShipDock $shipDock)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ShipDock $shipDock)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ShipDock $shipDock)
    {
        //
    }

    public function showDockByUserAndRoom($room, $user)
    {
        $shipDock = ShipDock::where('user_id', $user)
            ->where('room_id', $room)
            ->first();

        if (!$shipDock) {
            return response()->json(['message' => 'Ship dock not found'], 404);
        }

        return response()->json($shipDock, 200);
    }
}
