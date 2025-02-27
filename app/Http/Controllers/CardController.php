<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Deck;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function index()
    {
        return Card::with('decks')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => [
                'required',
                'string',
                'unique:cards,id',
            ],
            'priority' => 'required|string',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'quantity' => 'required|integer',
            'revenue' => 'required|integer',
        ]);

        $numericId = intval($validated['id']);
        if ($numericId < 1 || $numericId > 99999) {
            return response()->json([
                'message' => 'ID must be a number between 1 and 99999',
                'errors' => ['id' => ['Invalid ID range']]
            ], 422);
        }

        $validated['type'] = ($numericId % 5 === 0) ? 'Reefer' : 'Dry';

        $card = Card::create($validated);

        for ($i = 0; $i < $card->quantity; $i++) {
            $color = $this->generateContainerColor($card->destination);
            $card->containers()->create([
                'color' => $color,
            ]);
        }

        return response()->json($card->load('containers'), 201);
    }

    public function show(Card $card)
    {
        return $card;
    }

    public function update(Request $request, Card $card)
    {
        $validated = $request->validate([
            'type' => 'string',
            'priority' => 'string',
            'origin' => 'string',
            'destination' => 'string',
            'quantity' => 'integer',
            'revenue' => 'integer',
        ]);

        $card->update($validated);

        return response()->json($card, 200);
    }

    public function destroy(Card $card)
    {
        $card->delete();

        return response()->json(null, 204);
    }

    public function generate(Request $request, Deck $deck)
    {
        // Clear existing cards
        if ($deck->cards()->count() > 0) {
            foreach ($deck->cards as $card) {
                $deck->cards()->detach($card->id);
                $card->delete();
            }
        }

        $validated = $request->validate([
            'totalRevenueEachPort' => 'required|numeric',
            'totalContainerQuantityEachPort' => 'required|numeric',
            'salesCallCountEachPort' => 'required|numeric',
            'ports' => 'required|numeric',
            'quantityStandardDeviation' => 'required|numeric',
            'revenueStandardDeviation' => 'required|numeric',
        ]);

        $ports = $this->getPorts($validated['ports']);
        $basePriceMap = $this->getBasePriceMap();
        $targetRevenue = $validated['totalRevenueEachPort'];
        $targetContainers = $validated['totalContainerQuantityEachPort'];
        $salesCallsCount = $validated['salesCallCountEachPort'];

        $salesCalls = [];
        $id = 1;

        foreach ($ports as $originPort) {
            while (Card::where('id', $id)->exists()) {
                $id++;
            }

            // Pre-distribute containers (ensuring total 15)
            $quantities = array_fill(0, $salesCallsCount, 1);
            $remainingContainers = $targetContainers - $salesCallsCount;

            // Distribute remaining containers randomly
            while ($remainingContainers > 0) {
                $index = rand(0, $salesCallsCount - 1);
                $quantities[$index]++;
                $remainingContainers--;
            }

            $portRevenue = 0;
            $portCalls = [];

            for ($i = 0; $i < $salesCallsCount; $i++) {
                $containerType = ($id % 5 == 0) ? "reefer" : "dry";

                do {
                    $destinationPort = $ports[array_rand($ports)];
                } while ($destinationPort === $originPort);

                $key = "$originPort-$destinationPort-$containerType";
                $basePrice = $basePriceMap[$key] ?? 10000000;

                // Round revenue per container to nearest 50,000
                $revenuePerContainer = round(
                    ($basePrice + $this->randomGaussian() * $validated['revenueStandardDeviation']) / 50000
                ) * 50000;
                $revenuePerContainer = max(50000, $revenuePerContainer);

                $revenue = $revenuePerContainer * $quantities[$i];
                $portRevenue += $revenue;

                $portCalls[] = [
                    'id' => (string)$id, // Convert to string as per validation
                    'type' => $containerType,
                    'priority' => rand(0, 1) ? "Committed" : "Non-Committed",
                    'origin' => $originPort,
                    'destination' => $destinationPort,
                    'quantity' => $quantities[$i],
                    'revenue' => $revenue,
                    'revenuePerContainer' => $revenuePerContainer
                ];

                $id++;
            }

            // Scale revenues to match target while maintaining 50,000 increments
            $revenueFactor = $targetRevenue / $portRevenue;
            foreach ($portCalls as &$call) {
                $newRevenuePerContainer = round(($call['revenuePerContainer'] * $revenueFactor) / 50000) * 50000;
                $call['revenue'] = $newRevenuePerContainer * $call['quantity'];
                unset($call['revenuePerContainer']);
            }

            // Fix any remaining difference in largest call
            $actualRevenue = array_sum(array_column($portCalls, 'revenue'));
            $remainingRevenue = $targetRevenue - $actualRevenue;
            if ($remainingRevenue != 0) {
                $maxRevenueIndex = array_search(max(array_column($portCalls, 'revenue')), array_column($portCalls, 'revenue'));
                $portCalls[$maxRevenueIndex]['revenue'] += $remainingRevenue;
            }

            $salesCalls = array_merge($salesCalls, $portCalls);
        }

        // Save to database
        foreach ($salesCalls as $salesCallData) {
            $salesCall = Card::create([
                'id' => $salesCallData['id'],
                'type' => $salesCallData['type'],
                'priority' => $salesCallData['priority'],
                'origin' => $salesCallData['origin'],
                'destination' => $salesCallData['destination'],
                'quantity' => $salesCallData['quantity'],
                'revenue' => $salesCallData['revenue'],
            ]);

            $deck->cards()->attach($salesCall->id);

            for ($i = 0; $i < $salesCallData['quantity']; $i++) {
                $salesCall->containers()->create([
                    'color' => $this->generateContainerColor($salesCallData['destination']),
                    'type' => $salesCallData['type'],
                ]);
            }
        }

        return response()->json($deck->load('cards'), 201);
    }

    private function getPorts($portsCount)
    {
        $ports = [
            4 => ['SBY', 'MKS', 'MDN', 'JYP'],
            5 => ['SBY', 'MKS', 'MDN', 'JYP', 'BPN'],
            6 => ['SBY', 'MKS', 'MDN', 'JYP', 'BPN', 'BKS'],
            7 => ['SBY', 'MKS', 'MDN', 'JYP', 'BPN', 'BKS', 'BGR'],
            8 => ['SBY', 'MKS', 'MDN', 'JYP', 'BPN', 'BKS', 'BGR', 'BTH'],
        ];

        return $ports[$portsCount] ?? [];
    }

    private function getBasePriceMap()
    {
        return [
            "SBY-MKS-Reefer" => 30000000,
            "SBY-MKS-Dry" => 18000000,
            "SBY-MDN-Reefer" => 11000000,
            "SBY-MDN-Dry" => 6000000,
            "SBY-JYP-Reefer" => 24000000,
            "SBY-JYP-Dry" => 16200000,
            "MDN-SBY-Reefer" => 22000000,
            "MDN-SBY-Dry" => 13000000,
            "MDN-MKS-Reefer" => 24000000,
            "MDN-MKS-Dry" => 14000000,
            "MDN-JYP-Reefer" => 22000000,
            "MDN-JYP-Dry" => 14000000,
            "MKS-SBY-Reefer" => 18000000,
            "MKS-SBY-Dry" => 10000000,
            "MKS-MDN-Reefer" => 20000000,
            "MKS-MDN-Dry" => 12000000,
            "MKS-JYP-Reefer" => 24000000,
            "MKS-JYP-Dry" => 16000000,
            "JYP-SBY-Reefer" => 19000000,
            "JYP-SBY-Dry" => 13000000,
            "JYP-MKS-Reefer" => 23000000,
            "JYP-MKS-Dry" => 13000000,
            "JYP-MDN-Reefer" => 17000000,
            "JYP-MDN-Dry" => 11000000,
        ];
    }

    private function randomGaussian()
    {
        $u = mt_rand() / mt_getrandmax();
        $v = mt_rand() / mt_getrandmax();
        return sqrt(-2 * log($u)) * cos(2 * pi() * $v);
    }

    private function generateContainerColor($destination)
    {
        $colorMap = [
            'SBY' => 'red',
            'MKS' => 'blue',
            'MDN' => 'green',
            'JYP' => 'yellow',
            'BPN' => 'purple',
            'BKS' => 'orange',
            'BGR' => 'pink',
            'BTH' => 'brown',
        ];

        return $colorMap[$destination] ?? 'gray';
    }
}
