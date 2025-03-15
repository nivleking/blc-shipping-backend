<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\MarketIntelligence;
use Illuminate\Http\Request;

class MarketIntelligenceController extends Controller
{
    public function exists(Deck $deck)
    {
        $exists = $deck->marketIntelligences()->exists();
        return response()->json([
            'exists' => $exists
        ]);
    }

    /**
     * Get all market intelligence data for a deck
     */
    public function index(Deck $deck)
    {
        $marketIntelligences = $deck->marketIntelligences()->orderBy('created_at', 'desc')->get();
        return response()->json($marketIntelligences);
    }

    /**
     * Get active market intelligence for a deck
     */
    public function getActive(Deck $deck)
    {
        $marketIntelligence = $deck->activeMarketIntelligence();

        if (!$marketIntelligence) {
            return response()->json([
                'message' => 'No market intelligence data found for this deck'
            ], 404);
        }

        return response()->json($marketIntelligence);
    }

    /**
     * Store a new market intelligence data for a deck
     */
    public function store(Request $request, Deck $deck)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price_data' => 'required|array',
        ]);

        // Validate price data format
        $priceData = $request->price_data;
        $validPorts = ["SBY", "MKS", "MDN", "JYP", "BPN", "BKS", "BGR", "BTH", "AMQ", "SMR"];
        $validTypes = ["Dry", "Reefer"];

        foreach ($priceData as $key => $price) {
            $parts = explode('-', $key);
            if (count($parts) !== 3) {
                return response()->json([
                    'errors' => ['price_data' => ['Invalid key format. Expected format: Origin-Destination-Type']]
                ], 422);
            }

            [$origin, $destination, $type] = $parts;

            if (!in_array($origin, $validPorts)) {
                return response()->json([
                    'errors' => ['price_data' => ["Invalid origin port: $origin"]]
                ], 422);
            }

            if (!in_array($destination, $validPorts)) {
                return response()->json([
                    'errors' => ['price_data' => ["Invalid destination port: $destination"]]
                ], 422);
            }

            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'errors' => ['price_data' => ["Invalid container type: $type"]]
                ], 422);
            }

            if (!is_numeric($price) || $price <= 0) {
                return response()->json([
                    'errors' => ['price_data' => ["Invalid price for $origin-$destination-$type: Price must be a positive number"]]
                ], 422);
            }
        }

        $marketIntelligence = $deck->marketIntelligences()->create([
            'name' => $request->name,
            'price_data' => $priceData,
        ]);

        return response()->json($marketIntelligence, 201);
    }

    /**
     * Update market intelligence
     */
    public function update(Request $request, MarketIntelligence $marketIntelligence)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'price_data' => 'array',
        ]);

        // Validate each price entry if price_data is provided
        if ($request->has('price_data')) {
            $priceData = $request->price_data;
            $validPorts = ["SBY", "MKS", "MDN", "JYP", "BPN", "BKS", "BGR", "BTH", "AMQ", "SMR"];
            $validTypes = ["Dry", "Reefer"];

            foreach ($priceData as $key => $price) {
                $parts = explode('-', $key);
                if (count($parts) !== 3) {
                    return response()->json([
                        'errors' => ['price_data' => ['Invalid key format. Expected format: Origin-Destination-Type']]
                    ], 422);
                }

                [$origin, $destination, $type] = $parts;

                if (!in_array($origin, $validPorts)) {
                    return response()->json([
                        'errors' => ['price_data' => ["Invalid origin port: $origin"]]
                    ], 422);
                }

                if (!in_array($destination, $validPorts)) {
                    return response()->json([
                        'errors' => ['price_data' => ["Invalid destination port: $destination"]]
                    ], 422);
                }

                if (!in_array($type, $validTypes)) {
                    return response()->json([
                        'errors' => ['price_data' => ["Invalid container type: $type"]]
                    ], 422);
                }

                if (!is_numeric($price) || $price <= 0) {
                    return response()->json([
                        'errors' => ['price_data' => ["Invalid price for $origin-$destination-$type: Price must be a positive number"]]
                    ], 422);
                }
            }
        }

        // Update the record with the validated data
        $marketIntelligence->update($request->all());
        $marketIntelligence->refresh(); // Refresh the model to get updated timestamp

        return response()->json($marketIntelligence);
    }

    /**
     * Get specific market intelligence
     */
    public function show(MarketIntelligence $marketIntelligence)
    {
        return response()->json($marketIntelligence);
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
        // Get default price map similar to what's in CardController
        $basePriceMap = $this->getDefaultBasePriceMap();

        $marketIntelligence = $deck->marketIntelligences()->create([
            'name' => 'Default Market Intelligence',
            'price_data' => $basePriceMap,
        ]);

        return response()->json($marketIntelligence, 201);
    }

    /**
     * Get default base price map
     */
    private function getDefaultBasePriceMap()
    {
        return [
            // Existing SBY routes
            "SBY-MKS-Reefer" => 30000000,
            "SBY-MKS-Dry" => 18000000,
            "SBY-MDN-Reefer" => 11000000,
            "SBY-MDN-Dry" => 6000000,
            "SBY-JYP-Reefer" => 24000000,
            "SBY-JYP-Dry" => 16200000,
            "SBY-BPN-Reefer" => 28000000,
            "SBY-BPN-Dry" => 17000000,
            "SBY-BKS-Reefer" => 26000000,
            "SBY-BKS-Dry" => 15000000,
            "SBY-BGR-Reefer" => 25000000,
            "SBY-BGR-Dry" => 14000000,
            "SBY-BTH-Reefer" => 27000000,
            "SBY-BTH-Dry" => 16000000,
            "SBY-AMQ-Reefer" => 32000000,
            "SBY-AMQ-Dry" => 19000000,
            "SBY-SMR-Reefer" => 29000000,
            "SBY-SMR-Dry" => 17000000,

            // Existing MDN routes
            "MDN-SBY-Reefer" => 22000000,
            "MDN-SBY-Dry" => 13000000,
            "MDN-MKS-Reefer" => 24000000,
            "MDN-MKS-Dry" => 14000000,
            "MDN-JYP-Reefer" => 22000000,
            "MDN-JYP-Dry" => 14000000,
            "MDN-BPN-Reefer" => 26000000,
            "MDN-BPN-Dry" => 15000000,
            "MDN-BKS-Reefer" => 25000000,
            "MDN-BKS-Dry" => 14000000,
            "MDN-BGR-Reefer" => 24000000,
            "MDN-BGR-Dry" => 13000000,
            "MDN-BTH-Reefer" => 23000000,
            "MDN-BTH-Dry" => 12000000,
            "MDN-AMQ-Reefer" => 30000000,
            "MDN-AMQ-Dry" => 18000000,
            "MDN-SMR-Reefer" => 28000000,
            "MDN-SMR-Dry" => 16000000,

            // Existing MKS routes
            "MKS-SBY-Reefer" => 18000000,
            "MKS-SBY-Dry" => 10000000,
            "MKS-MDN-Reefer" => 20000000,
            "MKS-MDN-Dry" => 12000000,
            "MKS-JYP-Reefer" => 24000000,
            "MKS-JYP-Dry" => 16000000,
            "MKS-BPN-Reefer" => 25000000,
            "MKS-BPN-Dry" => 15000000,
            "MKS-BKS-Reefer" => 23000000,
            "MKS-BKS-Dry" => 13000000,
            "MKS-BGR-Reefer" => 22000000,
            "MKS-BGR-Dry" => 12000000,
            "MKS-BTH-Reefer" => 26000000,
            "MKS-BTH-Dry" => 15000000,
            "MKS-AMQ-Reefer" => 28000000,
            "MKS-AMQ-Dry" => 17000000,
            "MKS-SMR-Reefer" => 27000000,
            "MKS-SMR-Dry" => 16000000,

            // Existing JYP routes
            "JYP-SBY-Reefer" => 19000000,
            "JYP-SBY-Dry" => 13000000,
            "JYP-MKS-Reefer" => 23000000,
            "JYP-MKS-Dry" => 13000000,
            "JYP-MDN-Reefer" => 17000000,
            "JYP-MDN-Dry" => 11000000,
            "JYP-BPN-Reefer" => 24000000,
            "JYP-BPN-Dry" => 14000000,
            "JYP-BKS-Reefer" => 22000000,
            "JYP-BKS-Dry" => 12000000,
            "JYP-BGR-Reefer" => 21000000,
            "JYP-BGR-Dry" => 11000000,
            "JYP-BTH-Reefer" => 25000000,
            "JYP-BTH-Dry" => 14000000,
            "JYP-AMQ-Reefer" => 29000000,
            "JYP-AMQ-Dry" => 18000000,
            "JYP-SMR-Reefer" => 26000000,
            "JYP-SMR-Dry" => 15000000,

            // New BPN routes
            "BPN-SBY-Reefer" => 20000000,
            "BPN-SBY-Dry" => 12000000,
            "BPN-MKS-Reefer" => 22000000,
            "BPN-MKS-Dry" => 13000000,
            "BPN-MDN-Reefer" => 24000000,
            "BPN-MDN-Dry" => 14000000,
            "BPN-JYP-Reefer" => 21000000,
            "BPN-JYP-Dry" => 12000000,
            "BPN-BKS-Reefer" => 23000000,
            "BPN-BKS-Dry" => 13000000,
            "BPN-BGR-Reefer" => 22000000,
            "BPN-BGR-Dry" => 12000000,
            "BPN-BTH-Reefer" => 25000000,
            "BPN-BTH-Dry" => 15000000,
            "BPN-AMQ-Reefer" => 28000000,
            "BPN-AMQ-Dry" => 17000000,
            "BPN-SMR-Reefer" => 24000000,
            "BPN-SMR-Dry" => 14000000,

            // New BKS routes
            "BKS-SBY-Reefer" => 21000000,
            "BKS-SBY-Dry" => 12000000,
            "BKS-MKS-Reefer" => 23000000,
            "BKS-MKS-Dry" => 13000000,
            "BKS-MDN-Reefer" => 25000000,
            "BKS-MDN-Dry" => 15000000,
            "BKS-JYP-Reefer" => 22000000,
            "BKS-JYP-Dry" => 12000000,
            "BKS-BPN-Reefer" => 24000000,
            "BKS-BPN-Dry" => 14000000,
            "BKS-BGR-Reefer" => 20000000,
            "BKS-BGR-Dry" => 11000000,
            "BKS-BTH-Reefer" => 26000000,
            "BKS-BTH-Dry" => 16000000,
            "BKS-AMQ-Reefer" => 29000000,
            "BKS-AMQ-Dry" => 18000000,
            "BKS-SMR-Reefer" => 25000000,
            "BKS-SMR-Dry" => 15000000,

            // New BGR routes
            "BGR-SBY-Reefer" => 22000000,
            "BGR-SBY-Dry" => 13000000,
            "BGR-MKS-Reefer" => 24000000,
            "BGR-MKS-Dry" => 14000000,
            "BGR-MDN-Reefer" => 26000000,
            "BGR-MDN-Dry" => 16000000,
            "BGR-JYP-Reefer" => 23000000,
            "BGR-JYP-Dry" => 13000000,
            "BGR-BPN-Reefer" => 25000000,
            "BGR-BPN-Dry" => 15000000,
            "BGR-BKS-Reefer" => 21000000,
            "BGR-BKS-Dry" => 12000000,
            "BGR-BTH-Reefer" => 27000000,
            "BGR-BTH-Dry" => 17000000,
            "BGR-AMQ-Reefer" => 30000000,
            "BGR-AMQ-Dry" => 19000000,
            "BGR-SMR-Reefer" => 26000000,
            "BGR-SMR-Dry" => 16000000,

            // New BTH routes
            "BTH-SBY-Reefer" => 23000000,
            "BTH-SBY-Dry" => 14000000,
            "BTH-MKS-Reefer" => 25000000,
            "BTH-MKS-Dry" => 15000000,
            "BTH-MDN-Reefer" => 27000000,
            "BTH-MDN-Dry" => 17000000,
            "BTH-JYP-Reefer" => 24000000,
            "BTH-JYP-Dry" => 14000000,
            "BTH-BPN-Reefer" => 26000000,
            "BTH-BPN-Dry" => 16000000,
            "BTH-BKS-Reefer" => 22000000,
            "BTH-BKS-Dry" => 13000000,
            "BTH-BGR-Reefer" => 23000000,
            "BTH-BGR-Dry" => 14000000,
            "BTH-AMQ-Reefer" => 31000000,
            "BTH-AMQ-Dry" => 20000000,
            "BTH-SMR-Reefer" => 27000000,
            "BTH-SMR-Dry" => 17000000,

            // New AMQ routes
            "AMQ-SBY-Reefer" => 24000000,
            "AMQ-SBY-Dry" => 15000000,
            "AMQ-MKS-Reefer" => 26000000,
            "AMQ-MKS-Dry" => 16000000,
            "AMQ-MDN-Reefer" => 28000000,
            "AMQ-MDN-Dry" => 18000000,
            "AMQ-JYP-Reefer" => 25000000,
            "AMQ-JYP-Dry" => 15000000,
            "AMQ-BPN-Reefer" => 27000000,
            "AMQ-BPN-Dry" => 17000000,
            "AMQ-BKS-Reefer" => 23000000,
            "AMQ-BKS-Dry" => 14000000,
            "AMQ-BGR-Reefer" => 24000000,
            "AMQ-BGR-Dry" => 15000000,
            "AMQ-BTH-Reefer" => 28000000,
            "AMQ-BTH-Dry" => 18000000,
            "AMQ-SMR-Reefer" => 25000000,
            "AMQ-SMR-Dry" => 15000000,

            // New SMR routes
            "SMR-SBY-Reefer" => 25000000,
            "SMR-SBY-Dry" => 16000000,
            "SMR-MKS-Reefer" => 27000000,
            "SMR-MKS-Dry" => 17000000,
            "SMR-MDN-Reefer" => 29000000,
            "SMR-MDN-Dry" => 19000000,
            "SMR-JYP-Reefer" => 26000000,
            "SMR-JYP-Dry" => 16000000,
            "SMR-BPN-Reefer" => 28000000,
            "SMR-BPN-Dry" => 18000000,
            "SMR-BKS-Reefer" => 24000000,
            "SMR-BKS-Dry" => 15000000,
            "SMR-BGR-Reefer" => 25000000,
            "SMR-BGR-Dry" => 16000000,
            "SMR-BTH-Reefer" => 29000000,
            "SMR-BTH-Dry" => 19000000,
            "SMR-AMQ-Reefer" => 26000000,
            "SMR-AMQ-Dry" => 16000000,
        ];
    }
}
