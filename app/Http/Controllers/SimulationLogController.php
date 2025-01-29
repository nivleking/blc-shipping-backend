<?php

namespace App\Http\Controllers;

use App\Models\SimulationLog;
use Illuminate\Http\Request;

class SimulationLogController extends Controller
{
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'arena' => 'required|array',
            'user_id' => 'required|exists:users,id',
            'room_id' => 'required|exists:rooms,id',
            'revenue' => 'required|numeric|min:0',
        ]);

        $simulationLog = SimulationLog::create($validatedData);

        return response()->json($simulationLog, 201);
    }

    public function show(SimulationLog $simulationLog)
    {
        //
    }

    public function update(Request $request, SimulationLog $simulationLog)
    {
        //
    }

    public function destroy(SimulationLog $simulationLog)
    {
        // Delete
        // $simulationLog->delete();
    }
}
