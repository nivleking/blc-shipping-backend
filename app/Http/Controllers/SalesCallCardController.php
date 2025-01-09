<?php

namespace App\Http\Controllers;

use App\Models\SalesCallCard;
use Illuminate\Http\Request;

class SalesCallCardController extends Controller
{
    public function index()
    {
        return SalesCallCard::with('decks')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'priority' => 'required|string',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'quantity' => 'required|integer',
            'revenue' => 'required|integer',
        ]);

        $salesCallCard = SalesCallCard::create($validated);

        return response()->json($salesCallCard, 201);
    }

    public function show(SalesCallCard $salesCallCard)
    {
        return $salesCallCard;
    }

    public function update(Request $request, SalesCallCard $salesCallCard)
    {
        $validated = $request->validate([
            'type' => 'string',
            'priority' => 'string',
            'origin' => 'string',
            'destination' => 'string',
            'quantity' => 'integer',
            'revenue' => 'integer',
        ]);

        $salesCallCard->update($validated);

        return response()->json($salesCallCard, 200);
    }

    public function destroy(SalesCallCard $salesCallCard)
    {
        $salesCallCard->delete();

        return response()->json(null, 204);
    }

    public function generate()
    {
        // Delete all Sales Call Cards
        if (SalesCallCard::count() > 0) {
            SalesCallCard::truncate();
        }

        $maxTotalRevenueEachPort = 250000000;
        $maxTotalContainerQuantityEachPort = 15;
        $maxSalesCardEachPort = 8;

        $ports = ['SBY', 'MKS', 'MDN', 'JYP'];
        $salesCalls = [];
        $revenuePerPort = array_fill_keys($ports, 0);
        $quantityPerPort = array_fill_keys($ports, 0);

        $basePriceMap = [
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

        $quantityStandardDeviation = 1.0;
        $revenueStandardDeviation = 500000;
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
                } while ($destinationPort == $originPort);

                $key = "$originPort-$destinationPort-$containerType";
                $basePrice = $basePriceMap[$key] ?? 10000000;

                $revenuePerContainer = max(50000, round($basePrice + $this->randomGaussian() * $revenueStandardDeviation, -4));
                $quantity = min(max(1, round($avgQuantityPerCall + $this->randomGaussian() * $quantityStandardDeviation)), $remainingQuantity);
                $totalRevenue = $revenuePerContainer * $quantity;

                if ($totalRevenue > $remainingRevenue) {
                    $totalRevenue = $remainingRevenue;
                }

                $priority = rand(0, 1) ? "committed" : "non-committed";

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

        // Save generated sales calls to the database
        foreach ($salesCalls as $salesCallData) {
            $salesCall = SalesCallCard::create($salesCallData);

            // Generate Containers for this Sales Call Card
            for ($i = 0; $i < $salesCall->quantity; $i++) {
                $color = $this->generateContainerColor($salesCall->destination); // Tentukan warna berdasarkan destination
                $salesCall->containers()->create([
                    'color' => $color,
                ]);
            }
        }

        // Return all sales call cards
        $allSalesCallCards = SalesCallCard::all();

        return response()->json($allSalesCallCards, 200);
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
            'SBY' => 'blue',
            'MKS' => 'red',
            'MDN' => 'green',
            'JYP' => 'yellow',
        ];

        return $colorMap[$destination] ?? 'gray';
    }
}
