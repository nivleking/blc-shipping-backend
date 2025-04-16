<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\MarketIntelligence;
use App\Utilities\UtilitiesHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarketIntelligenceController extends Controller
{

    use UtilitiesHelper;

    /**
     * Get market intelligence for a deck
     */
    public function forDeck(Deck $deck)
    {
        $marketIntelligence = $deck->marketIntelligence;

        if (!$marketIntelligence) {
            return response()->json([
                'message' => 'No market intelligence data found for this deck'
            ], 404);
        }

        return response()->json($marketIntelligence);
    }

    /**
     * Store or update market intelligence for a deck
     */
    public function storeOrUpdate(Request $request, Deck $deck)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price_data' => 'required|array',
            'penalties' => 'nullable|array',
        ]);

        // Validate price data format
        $priceData = $request->price_data;
        $validPorts = ["SBY", "MKS", "MDN", "JYP", "BPN", "BKS", "BGR", "BTH", "AMQ", "SMR"];
        $validTypes = ["Dry", "Reefer"];

        foreach ($priceData as $key => $price) {
            $parts = explode('-', $key);
            if (count($parts) !== 3) {
                return response()->json([
                    'message' => 'Invalid price data format',
                    'detail' => "Key should be in format Origin-Destination-Type: {$key}"
                ], 422);
            }

            [$origin, $destination, $type] = $parts;

            if (!in_array($origin, $validPorts)) {
                return response()->json([
                    'message' => 'Invalid port code',
                    'detail' => "Origin port '{$origin}' is invalid"
                ], 422);
            }

            if (!in_array($destination, $validPorts)) {
                return response()->json([
                    'message' => 'Invalid port code',
                    'detail' => "Destination port '{$destination}' is invalid"
                ], 422);
            }

            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'message' => 'Invalid container type',
                    'detail' => "Type '{$type}' should be either 'Dry' or 'Reefer'"
                ], 422);
            }

            if (!is_numeric($price) || $price <= 0) {
                return response()->json([
                    'message' => 'Invalid price value',
                    'detail' => "Price for {$key} must be a positive number"
                ], 422);
            }
        }

        // Validate penalties if they exist
        $penalties = $request->penalties ?? $this->getUnrolledPenalties();

        // Create or update market intelligence
        $marketIntelligence = $deck->marketIntelligence;

        if ($marketIntelligence) {
            // Update existing
            $marketIntelligence->update([
                'name' => $request->name,
                'price_data' => $priceData,
                'penalties' => $penalties,
            ]);
            $action = 'updated';
        } else {
            // Create new
            $marketIntelligence = $deck->marketIntelligence()->create([
                'name' => $request->name,
                'price_data' => $priceData,
                'penalties' => $penalties,
            ]);
            $action = 'created';
        }

        return response()->json([
            'message' => "Market intelligence {$action} successfully",
            'data' => $marketIntelligence
        ], $action === 'created' ? 201 : 200);
    }

    /**
     * Delete market intelligence
     */
    public function destroy(MarketIntelligence $marketIntelligence)
    {
        $marketIntelligence->delete();
        return response()->json(null, 204);
    }

    /**
     * Generate default market intelligence data for a deck
     */
    public function generateDefault(Deck $deck)
    {
        // Get default price map
        $basePriceMap = $this->getBasePriceMap();

        // Get default penalties for unrolled containers
        $penalties = $this->getUnrolledPenalties();

        // Delete existing market intelligence if exists
        if ($deck->marketIntelligence) {
            $deck->marketIntelligence->delete();
        }

        // Create new market intelligence with prices and penalties
        $marketIntelligence = $deck->marketIntelligence()->create([
            'name' => 'Default Market Intelligence',
            'price_data' => $basePriceMap,
            'penalties' => $penalties
        ]);

        return response()->json($marketIntelligence, 201);
    }
}
