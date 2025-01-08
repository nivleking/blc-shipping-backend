<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesCallCardRequest;
use App\Http\Requests\UpdateSalesCallCardRequest;
use App\Models\SalesCallCard;
use Illuminate\Http\Request;

class SalesCallCardController extends Controller
{
    public function index()
    {
        $salesCallCards = SalesCallCard::all();

        return response()->json($salesCallCards, 200);
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

    public function generateSalesCallCards(Request $request)
    {
        $validated = $request->validate([
            'maximum_revenue' => 'required|integer',
            'maximum_quantity' => 'required|integer',
            'total_sales_call_cards' => 'required|integer',
            'ports' => 'required|array',
            'std_dev_revenue' => 'required|numeric',
            'std_dev_quantity' => 'required|numeric',
        ]);

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
                    'id' => $id,
                    'quantity' => $quantity,
                    'revenue' => $totalRevenue,
                    'priority' => $priority,
                    'containerType' => $containerType,
                    'originPort' => $originPort,
                    'destinationPort' => $destinationPort,
                ];
                $salesCalls[] = $salesCall;

                $revenuePerPort[$originPort] += $totalRevenue;
                $quantityPerPort[$originPort] += $quantity;

                $salesCallsGenerated++;
                $id++;
            }
        }

        // After generating the sales calls and before final output, distribute the remaining revenue and quantity more evenly
        foreach ($ports as $port) {
            $currentRevenue = $revenuePerPort[$port];
            $currentQuantity = $quantityPerPort[$port];

            $remainingRevenue = $maxTotalRevenueEachPort - $currentRevenue;
            $remainingQuantity = $maxTotalContainerQuantityEachPort - $currentQuantity;

            $portCalls = array_filter($salesCalls, fn($call) => $call['originPort'] == $port);
            $eligibleCalls = array_filter($portCalls, fn($call) => $call['quantity'] > 1);

            // Distribute remaining revenue to eligible calls
            $revenuePerEligibleCall = intdiv($remainingRevenue, count($eligibleCalls));
            foreach ($eligibleCalls as &$call) {
                $call['revenue'] += $revenuePerEligibleCall;
                $revenuePerPort[$port] += $revenuePerEligibleCall;
            }

            // Adjust quantities for the call with the smallest quantity if needed
            if ($remainingQuantity != 0) {
                $minQuantityCall = array_reduce($portCalls, fn($minCall, $call) => $call['quantity'] < $minCall['quantity'] ? $call : $minCall);
                $minQuantityCall['quantity'] += $remainingQuantity;
                $quantityPerPort[$port] = $maxTotalContainerQuantityEachPort;
            }
        }

        foreach ($ports as $port) {
            $currentRevenue = $revenuePerPort[$port];
            $remainingRevenue = $maxTotalRevenueEachPort - $currentRevenue;

            if ($remainingRevenue > 0) {
                foreach ($salesCalls as &$call) {
                    if ($call['originPort'] == $port) {
                        $call['revenue'] += $remainingRevenue;
                        $revenuePerPort[$port] += $remainingRevenue;
                        break;
                    }
                }
            }
        }

        return response()->json($salesCalls, 200);
    }

    private function randomGaussian()
    {
        $u = mt_rand() / mt_getrandmax();
        $v = mt_rand() / mt_getrandmax();
        return sqrt(-2 * log($u)) * cos(2 * pi() * $v);
    }
}
