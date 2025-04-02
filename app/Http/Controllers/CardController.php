<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Deck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CardController extends Controller
{
    public function index()
    {
        return Card::with('decks')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'priority' => 'required|string',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'quantity' => 'required|integer',
            'revenue' => 'required|integer',
            'mode' => 'string',
        ]);

        $numericId = intval($validated['id']);
        // if ($numericId < 1 || $numericId > 99999) {
        //     return response()->json([
        //         'message' => 'ID must be a number between 1 and 99999',
        //         'errors' => ['id' => ['Invalid ID range']]
        //     ], 422);
        // }

        if (Card::where('id', $validated['id'])->exists()) {
            if (isset($validated['mode']) && $validated['mode'] == "auto_generate") {
                $validated['id'] = $this->getNextAvailableId();
                $numericId = intval($validated['id']);
            } else {
                return response()->json([
                    'message' => 'Card with this ID already exists',
                ], 422);
            }
        }

        // Ensure type is consistently formatted
        $validated['type'] = ($numericId % 5 === 0) ? 'reefer' : 'dry';

        $card = Card::create($validated);

        for ($i = 0; $i < $card->quantity; $i++) {
            $color = $this->generateContainerColor($card->destination);
            $card->containers()->create([
                'color' => $color,
                'type' => $validated['type'],
            ]);
        }

        return response()->json($card->load('containers'), 201);
    }

    public function show(Card $card)
    {
        return $card;
    }

    private function getNextAvailableId()
    {
        $id = 1;
        while (Card::where('id', (string)$id)->exists()) {
            $id++;
        }
        return (string)$id;
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

        // Store the old values for comparison
        $oldType = $card->type;
        $oldDestination = $card->destination;
        $oldQuantity = $card->quantity;

        // Update the card
        $card->update($validated);

        // If destination has changed, update all container colors
        if (isset($validated['destination']) && $oldDestination !== $validated['destination']) {
            // Get the new color based on the destination
            $newColor = $this->generateContainerColor($validated['destination']);

            // Update all containers associated with this card
            $card->containers()->update(['color' => $newColor]);
        }

        // If type has changed, update all containers
        if (isset($validated['type']) && $oldType !== $validated['type']) {
            $card->containers()->update(['type' => $validated['type']]);
        }

        // Handle quantity changes (add or remove containers as needed)
        if (isset($validated['quantity']) && $oldQuantity !== $validated['quantity']) {
            if ($validated['quantity'] > $oldQuantity) {
                // Add new containers
                $containersToAdd = $validated['quantity'] - $oldQuantity;
                $containerType = $card->type;
                $containerColor = $this->generateContainerColor($card->destination);

                for ($i = 0; $i < $containersToAdd; $i++) {
                    $card->containers()->create([
                        'type' => $containerType,
                        'color' => $containerColor
                    ]);
                }
            } else if ($validated['quantity'] < $oldQuantity) {
                // Remove excess containers
                $containersToRemove = $oldQuantity - $validated['quantity'];
                $card->containers()->latest()->take($containersToRemove)->delete();
            }
        }

        // Return the updated card with its containers
        return response()->json($card->load('containers'), 200);
    }

    public function destroy(Card $card)
    {
        $card->delete();

        return response()->json(null, 204);
    }

    public function generate(Request $request, Deck $deck)
    {
        $this->destroyAllCardsInDeck($deck);
        $validated = $request->validate([
            'totalRevenueEachPort' => 'required|numeric',
            'totalContainerQuantityEachPort' => 'required|numeric',
            'salesCallCountEachPort' => 'required|numeric',
            'ports' => 'required|numeric',
            'quantityStandardDeviation' => 'required|numeric',
            'revenueStandardDeviation' => 'required|numeric',
            'useMarketIntelligence' => 'boolean',
        ]);

        $useMarketIntelligence = isset($validated['useMarketIntelligence']) ? $validated['useMarketIntelligence'] : true;

        $basePriceMap = [];
        $ports = $this->getPorts($validated['ports']);

        if ($useMarketIntelligence) {
            $marketIntelligence = $deck->marketIntelligence();

            if ($marketIntelligence && !empty($marketIntelligence->price_data)) {
                $basePriceMap = $marketIntelligence->price_data;

                $portSet = [];
                foreach (array_keys($marketIntelligence->price_data) as $key) {
                    $parts = explode('-', $key);
                    if (!in_array($parts[0], $portSet)) {
                        $portSet[] = $parts[0];
                    }
                }

                if (count($portSet) >= 2) {
                    $ports = $portSet;
                    $validated['ports'] = count($ports);
                }
            } else {
                $basePriceMap = $this->getBasePriceMap();
            }
        } else {
            $basePriceMap = $this->getBasePriceMap();
        }

        $targetRevenue = $validated['totalRevenueEachPort'];
        $targetContainers = $validated['totalContainerQuantityEachPort'];
        $salesCallsCount = $validated['salesCallCountEachPort'];

        $salesCalls = [];
        $id = 1;

        while (Card::where('id', (string)$id)->exists()) {
            $id++;
        }

        foreach ($ports as $originPort) {
            // Pre-distribute containers (ensuring total matches target)
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
                $containerType = ($id % 5 == 0) ? "Reefer" : "Dry";

                do {
                    $destinationPort = $ports[array_rand($ports)];
                } while ($destinationPort === $originPort);

                $key = "$originPort-$destinationPort-$containerType";

                $basePrice = isset($basePriceMap[$key]) ? $basePriceMap[$key] : 10000000;

                // Round revenue per container to nearest 50,000
                $revenuePerContainer = round(
                    ($basePrice + $this->randomGaussian() * $validated['revenueStandardDeviation']) / 50000
                ) * 50000;
                $revenuePerContainer = max(50000, $revenuePerContainer);

                $revenue = $revenuePerContainer * $quantities[$i];
                $portRevenue += $revenue;

                $portCalls[] = [
                    'id' => $id++,
                    'type' => strtolower($containerType),
                    'priority' => (rand(1, 100) <= 50) ? "Committed" : "Non-Committed",
                    'origin' => $originPort,
                    'destination' => $destinationPort,
                    'quantity' => $quantities[$i],
                    'revenue' => $revenue,
                ];
            }

            if ($portRevenue > 0) {
                $revenueFactor = $targetRevenue / $portRevenue;
                foreach ($portCalls as &$call) {
                    $call['revenue'] = round(($call['revenue'] * $revenueFactor) / 50000) * 50000;
                }
            }

            // Fix any remaining difference in largest call
            $actualRevenue = array_sum(array_column($portCalls, 'revenue'));
            $remainingRevenue = $targetRevenue - $actualRevenue;
            if ($remainingRevenue != 0 && count($portCalls) > 0) {
                // Find the largest revenue call
                $maxRevenueKey = 0;
                for ($i = 1; $i < count($portCalls); $i++) {
                    if ($portCalls[$i]['revenue'] > $portCalls[$maxRevenueKey]['revenue']) {
                        $maxRevenueKey = $i;
                    }
                }
                $portCalls[$maxRevenueKey]['revenue'] += $remainingRevenue;
            }

            $salesCalls = array_merge($salesCalls, $portCalls);
        }

        foreach ($salesCalls as $salesCallData) {
            $cardRequest = new Request([
                'id' => (string)$salesCallData['id'],
                'type' => $salesCallData['type'],
                'priority' => $salesCallData['priority'],
                'origin' => $salesCallData['origin'],
                'destination' => $salesCallData['destination'],
                'quantity' => $salesCallData['quantity'],
                'revenue' => $salesCallData['revenue'],
                'mode' => 'auto_generate',
            ]);

            $response = $this->store($cardRequest);
            $responseData = json_decode($response->getContent());
            $salesCall = Card::find($responseData->id);

            if ($salesCall) {
                $deck->cards()->attach($salesCall->id);
            }
        }

        return response()->json($deck->load('cards'), 201);
    }

    private function getPorts($portsCount)
    {
        $ports = [
            2 => ["SBY", "MKS"],
            3 => ["SBY", "MKS", "MDN"],
            4 => ["SBY", "MKS", "MDN", "JYP"],
            5 => ["SBY", "MKS", "MDN", "JYP", "BPN"],
            6 => ["SBY", "MKS", "MDN", "JYP", "BPN", "BKS"],
            7 => ["SBY", "MKS", "MDN", "JYP", "BPN", "BKS", "BGR"],
            8 => ["SBY", "MKS", "MDN", "JYP", "BPN", "BKS", "BGR", "BTH"],
            9 => ["SBY", "MKS", "MDN", "JYP", "BPN", "BKS", "BGR", "BTH", "AMQ"],
            10 => ["SBY", "MKS", "MDN", "JYP", "BPN", "BKS", "BGR", "BTH", "AMQ", "SMR"],
        ];

        return $ports[$portsCount] ?? [];
    }

    private function getBasePriceMap()
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
            'AMQ' => 'cyan',
            'SMR' => 'teal',
        ];

        return $colorMap[$destination] ?? 'gray';
    }

    public function importFromExcel(Request $request, Deck $deck)
    {
        $this->destroyAllCardsInDeck($deck);
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $dataRows = array_slice($rows, 11);

        $validPorts = ["SBY", "MKS", "MDN", "JYP", "BPN", "BKS", "BGR", "BTH"];
        $createdCards = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($dataRows as $index => $row) {
                if (empty($row[0])) continue;

                $id = trim($row[0]);
                $origin = strtoupper(trim($row[1]));
                $destination = strtoupper(trim($row[2]));
                $priority = trim($row[3]);
                $containerType = strtolower(trim($row[4]));
                $quantity = intval($row[5]);
                $revenuePerContainerRaw
                    = intval($row[6]);

                if (is_numeric($revenuePerContainerRaw)) {
                    $revenuePerContainer = intval($revenuePerContainerRaw);
                } else if (is_string($revenuePerContainerRaw)) {
                    $cleanValue = preg_replace('/[^0-9.]/', '', $revenuePerContainerRaw);
                    $revenuePerContainer = intval($cleanValue);
                }

                if (!$id || !preg_match('/^\d+$/', $id) || intval($id) < 1 || intval($id) > 99999) {
                    $errors[] = "Row " . ($index + 12) . ": Invalid ID \"$id\". Must be a number between 1-99999";
                    continue;
                }

                if (!in_array($origin, $validPorts)) {
                    $errors[] = "Row " . ($index + 12) . ": Invalid origin port \"$origin\"";
                    continue;
                }

                if (!in_array($destination, $validPorts)) {
                    $errors[] = "Row " . ($index + 12) . ": Invalid destination port \"$destination\"";
                    continue;
                }

                if ($origin === $destination) {
                    $errors[] = "Row " . ($index + 12) . ": Origin and destination cannot be the same";
                    continue;
                }

                if (!in_array($priority, ["Committed", "Non-Committed"])) {
                    $errors[] = "Row " . ($index + 12) . ": Invalid priority \"$priority\"";
                    continue;
                }

                if (!in_array(strtolower($containerType), ["dry", "reefer"])) {
                    $errors[] = "Row " . ($index + 12) . ": Invalid container type \"$containerType\"";
                    continue;
                }

                if ($quantity <= 0) {
                    $errors[] = "Row " . ($index + 12) . ": Invalid quantity";
                    continue;
                }

                if ($revenuePerContainer <= 0) {
                    $errors[] = "Row " . ($index + 12) . ": Invalid revenue per container";
                    continue;
                }

                // Ensure correct type based on ID
                $numericId = intval($id);
                $type = ($numericId % 5 === 0) ? 'reefer' : 'dry';

                $revenue = $quantity * $revenuePerContainer;

                if (Card::where('id', $id)->exists()) {
                    $errors[] = "Row " . ($index + 12) . ": Card with ID $id already exists";
                    continue;
                }

                $card = Card::create([
                    'id' => $id,
                    'type' => $type,
                    'priority' => $priority,
                    'origin' => $origin,
                    'destination' => $destination,
                    'quantity' => $quantity,
                    'revenue' => $revenue
                ]);

                for ($i = 0; $i < $quantity; $i++) {
                    $color = $this->generateContainerColor($destination);
                    $card->containers()->create([
                        'color' => $color,
                        'type' => $type,
                    ]);
                }

                $deck->cards()->attach($card->id);

                $createdCards[] = $card;
            }

            if (count($errors) > 0) {
                DB::rollBack();
                return response()->json(['errors' => $errors], 422);
            }

            DB::commit();

            return response()->json([
                'message' => 'Successfully imported ' . count($createdCards) . ' cards to deck',
                'cards' => $createdCards
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred during import',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function destroyAllCardsInDeck(Deck $deck)
    {
        // Clear existing cards
        if ($deck->cards()->count() > 0) {
            foreach ($deck->cards as $card) {
                $deck->cards()->detach($card->id);
                $card->delete();
            }
        }
    }
}
