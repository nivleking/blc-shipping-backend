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
            'priority' => 'required|string',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'quantity' => 'required|integer',
            'revenue' => 'required|integer',
        ]);

        $nextId = Card::max('id') + 1;
        $validated['type'] = ($nextId % 5 == 0) ? 'Reefer' : 'Dry';

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
        if ($deck->cards()->count() > 0) {
            foreach ($deck->cards as $card) {
                $deck->cards()->detach($card->id);
                $card->delete();
            }
        }

        $validated = $request->validate([
            'maxTotalRevenueEachPort' => 'required|numeric',
            'maxTotalContainerQuantityEachPort' => 'required|numeric',
            'maxSalesCardEachPort' => 'required|numeric',
            'ports' => 'required|numeric',
            'quantityStandardDeviation' => 'required|numeric',
            'revenueStandardDeviation' => 'required|numeric',
        ]);

        $maxTotalRevenueEachPort = $validated['maxTotalRevenueEachPort'];
        $maxTotalContainerQuantityEachPort = $validated['maxTotalContainerQuantityEachPort'];
        $maxSalesCardEachPort = $validated['maxSalesCardEachPort'];
        $ports = $this->getPorts($validated['ports']);
        $quantityStandardDeviation = $validated['quantityStandardDeviation'];
        $revenueStandardDeviation = $validated['revenueStandardDeviation'];

        $salesCalls = [];
        $revenuePerPort = array_fill_keys($ports, 0);
        $quantityPerPort = array_fill_keys($ports, 0);

        $basePriceMap = $this->getBasePriceMap();

        $id = 1;

        foreach ($ports as $originPort) {
            $salesCallsGenerated = 0;

            while ($salesCallsGenerated < $maxSalesCardEachPort) {
                $remainingQuantity = $maxTotalContainerQuantityEachPort - $quantityPerPort[$originPort];
                $remainingRevenue = $maxTotalRevenueEachPort - $revenuePerPort[$originPort];

                if ($remainingQuantity <= 0 || $remainingRevenue <= 0) {
                    break;
                }

                $avgQuantityPerCall = max(1, intdiv($remainingQuantity, $maxSalesCardEachPort - $salesCallsGenerated));
                $containerType = ($id % 5 == 0) ? "Reefer" : "Dry";
                do {
                    $destinationPort = $ports[array_rand($ports)];
                } while ($destinationPort === $originPort);

                $key = "$originPort-$destinationPort-$containerType";
                $basePrice = $basePriceMap[$key] ?? 10000000;

                $revenuePerContainer = max(50000, round($basePrice + $this->randomGaussian() * $revenueStandardDeviation, -4));
                $quantity = min(max(1, round($avgQuantityPerCall + $this->randomGaussian() * $quantityStandardDeviation)), $remainingQuantity);
                $totalRevenue = $revenuePerContainer * $quantity;

                if ($totalRevenue > $remainingRevenue) {
                    $totalRevenue = $remainingRevenue;
                }

                $priority = rand(0, 1) ? "Committed" : "Non-Committed";

                $salesCall = [
                    'type' => $containerType,
                    'priority' => $priority,
                    'origin' => $originPort,
                    'destination' => $destinationPort,
                    'quantity' => $quantity,
                    'revenue' => $totalRevenue,
                ];
                $salesCalls[] = $salesCall;

                $revenuePerPort[$originPort] += $totalRevenue;
                $quantityPerPort[$originPort] += $quantity;

                $salesCallsGenerated++;
                $id++;
            }
        }

        foreach ($ports as $port) {
            $currentRevenue = $revenuePerPort[$port];
            $currentQuantity = $quantityPerPort[$port];

            $remainingRevenue = $maxTotalRevenueEachPort - $currentRevenue;
            $remainingQuantity = $maxTotalContainerQuantityEachPort - $currentQuantity;

            $portCalls = array_filter($salesCalls, function ($call) use ($port) {
                return $call['origin'] === $port;
            });

            $eligibleCalls = array_filter($portCalls, function ($call) {
                return $call['quantity'] > 1;
            });

            if (count($eligibleCalls) > 0) {
                $revenuePerEligibleCall = intdiv($remainingRevenue, count($eligibleCalls));
                foreach ($eligibleCalls as &$call) {
                    $call['revenue'] += $revenuePerEligibleCall;
                    $revenuePerPort[$port] += $revenuePerEligibleCall;
                }
            }

            if ($remainingQuantity > 0) {
                usort($portCalls, function ($a, $b) {
                    return $a['quantity'] <=> $b['quantity'];
                });

                if (count($portCalls) > 0) {
                    $portCalls[0]['quantity'] += $remainingQuantity;
                    $quantityPerPort[$port] = $maxTotalContainerQuantityEachPort;
                }
            }
        }

        // Save generated sales calls to the database
        foreach ($salesCalls as $salesCallData) {
            $salesCall = Card::create([
                'type' => $salesCallData['type'],
                'priority' => $salesCallData['priority'],
                'origin' => $salesCallData['origin'],
                'destination' => $salesCallData['destination'],
                'quantity' => $salesCallData['quantity'],
                'revenue' => $salesCallData['revenue'],
            ]);
            $deck->cards()->attach($salesCall->id);

            for ($i = 0; $i < $salesCall->quantity; $i++) {
                $color = $this->generateContainerColor($salesCall->destination);
                $salesCall->containers()->create([
                    'color' => $color,
                ]);
            }
        }

        return response()->json(
            $deck->load('cards'),
            201
        );
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
