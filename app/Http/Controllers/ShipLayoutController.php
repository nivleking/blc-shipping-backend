<?php

namespace App\Http\Controllers;

use App\Models\ShipLayout;
use Illuminate\Http\Request;

class ShipLayoutController extends Controller
{

    public function index()
    {
        $layouts = ShipLayout::with('creator')->get();
        return response()->json($layouts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'bay_size' => 'required|array',
            'bay_size.rows' => 'required|integer|min:2|max:10',
            'bay_size.columns' => 'required|integer|min:2|max:10',
            'bay_count' => 'required|integer|min:1|max:10',
            'bay_types' => 'required|array|min:1',
            'bay_types.*' => 'required|in:dry,reefer'
        ]);

        try {
            $layout = ShipLayout::create([
                ...$validated,
                'created_by' => $request->user()->id
            ]);

            return response()->json($layout, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create layout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, ShipLayout $shipLayout)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'bay_size' => 'required|array',
            'bay_count' => 'required|integer|min:1|max:8',
            'bay_types' => 'nullable|array'
        ]);

        $shipLayout->update($validated);
        return response()->json($shipLayout);
    }

    public function destroy(ShipLayout $shipLayout)
    {
        $shipLayout->delete();
        return response()->json(['message' => 'Layout deleted successfully']);
    }
}
