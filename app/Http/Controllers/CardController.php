<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Deck;
use App\Utilities\UtilitiesHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CardController extends Controller
{
    use UtilitiesHelper;

    public function index()
    {
        return Card::with('decks')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'deck_id' => 'required|exists:decks,id',
            'priority' => 'required|string',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'quantity' => 'required|integer',
            'revenue' => 'required|integer',
            'mode' => 'string',
        ]);

        $numericId = intval($validated['id']);
        if ($numericId < 1) {
            return response()->json([
                'message' => 'Invalid ID',
                'errors' => ['id' => ['Invalid ID range']]
            ], 422);
        }

        if (Card::where('id', $validated['id'])
            ->where('deck_id', $validated['deck_id'])
            ->exists()
        ) {
            return response()->json([
                'message' => 'Card with this ID already exists in this deck',
            ], 422);
        }

        $validated['type'] = ($numericId % 5 === 0) ? 'reefer' : 'dry';
        $card = Card::create($validated);

        for ($i = 0; $i < $card->quantity; $i++) {
            $color = $this->generateContainerColor($card->destination);
            $card->containers()->create([
                'color' => $color,
                'type' => $validated['type'],
                'deck_id' => $validated['deck_id'],
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
            'deck_id' => 'required|exists:decks,id',
            'type' => 'string',
            'priority' => 'string',
            'origin' => 'string',
            'destination' => 'string',
            'quantity' => 'integer',
            'revenue' => 'integer',
        ]);

        $card = Card::where('id', $card->id)
            ->where('deck_id', $validated['deck_id'])
            ->firstOrFail();

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
                        'color' => $containerColor,
                        'deck_id' => $card->deck_id,
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

    public function destroy(Request $request, Card $card)
    {
        $validated = $request->validate([
            'deck_id' => 'required|exists:decks,id',
        ]);

        $card = Card::where('id', $card->id)
            ->where('deck_id', $validated['deck_id'])
            ->firstOrFail();
        $card->containers()->where('deck_id', $validated['deck_id'])->delete();
        Card::where('id', $card->id)
            ->where('deck_id', $validated['deck_id'])
            ->delete();

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

        while (Card::where('id', (string)$id)
            ->where('deck_id', $deck->id)
            ->exists()
        ) {
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
                'deck_id' => $deck->id,
                'type' => $salesCallData['type'],
                'priority' => $salesCallData['priority'],
                'origin' => $salesCallData['origin'],
                'destination' => $salesCallData['destination'],
                'quantity' => $salesCallData['quantity'],
                'revenue' => $salesCallData['revenue'],
                'mode' => 'auto_generate',
            ]);

            $response = $this->store($cardRequest);
        }

        return response()->json($deck->load('cards'), 201);
    }

    private function randomGaussian()
    {
        $u = mt_rand() / mt_getrandmax();
        $v = mt_rand() / mt_getrandmax();
        return sqrt(-2 * log($u)) * cos(2 * pi() * $v);
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

        $validPorts = ["SBY", "MDN", "MKS", "JYP", "BPN", "BKS", "BGR", "BTH", "AMQ", "SMR"];
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

                if (Card::where('id', $id)
                    ->where('deck_id', $deck->id)
                    ->exists()
                ) {
                    $errors[] = "Row " . ($index + 12) . ": Card with ID $id already exists in this deck";
                    continue;
                }

                $card = Card::create([
                    'id' => $id,
                    'deck_id' => $deck->id,
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
                        'deck_id' => $deck->id,
                    ]);
                }

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
        try {
            if ($deck->cards()->count() > 0) {
                foreach ($deck->cards as $card) {
                    $card->containers()
                        ->where('deck_id', $deck->id)
                        ->delete();
                    Card::where('id', $card->id)
                        ->where('deck_id', $deck->id)
                        ->delete();
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
