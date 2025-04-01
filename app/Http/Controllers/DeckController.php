<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Container;
use App\Models\Deck;
use App\Models\SalesCallCard;
use Illuminate\Http\Request;

class DeckController extends Controller
{
    public function index(Request $request)
    {
        $includeCards = $request->query('include_cards', false);

        $decks = Deck::with('cards')->get();

        $decks = $decks->map(function ($deck) use ($includeCards) {
            $cards = $deck->cards;
            $stats = [
                'totalCards' => $cards->count(),
                'totalPorts' => $cards->pluck('origin')->unique()->count(),
                'dryContainers' => $cards->where('type', 'dry')->count(),
                'reeferContainers' => $cards->where('type', 'reefer')->count(),
                'commitedCards' => $cards->where('priority', 'Committed')->count(),
                'nonCommitedCards' => $cards->where('priority', 'Non-Committed')->count(),
            ];

            $deckData = $deck->toArray();

            if (!$includeCards) {
                unset($deckData['cards']);
            }

            $deckData['stats'] = $stats;

            return $deckData;
        });

        return response()->json($decks);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $deck = Deck::create($validated);

        $this->createDefaultMarketIntelligence($deck);

        return response()->json($deck, 201);
    }

    private function createDefaultMarketIntelligence(Deck $deck)
    {
        $basePriceMap = $this->getDefaultBasePriceMap();

        $deck->marketIntelligence()->create([
            'name' => 'Default Market Intelligence',
            'price_data' => $basePriceMap,
        ]);
    }

    public function show(Request $request, Deck $deck)
    {
        $includeContainers = $request->query('include_containers', false);
        // Always load the deck with its cards
        $deck->load('cards');

        if ($includeContainers) {
            $cardIds = $deck->cards->pluck('id')->toArray();

            $containers = Container::whereIn('card_id', $cardIds)->get();

            return response()->json([
                'deck' => $deck,
                'containers' => $containers
            ], 200);
        }

        return response()->json($deck, 200);
    }

    public function update(Request $request, Deck $deck)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
        ]);

        $deck->update($validated);
        return response()->json($deck, 200);
    }

    public function destroy(Deck $deck)
    {
        $this->removeAllCards($deck);
        $deck->delete();
        return response()->json(null, 204);
    }

    public function showByDeck(Deck $deck)
    {
        return response()->json($deck->load('cards'), 200);
    }

    public function getOrigins(Deck $deck)
    {
        $origins = $deck->cards()->pluck('origin')->unique();
        return response()->json($origins, 200);
    }

    public function addCard(Request $request, Deck $deck)
    {
        $request->validate([
            'card_id' => 'required|exists:cards,id'
        ]);

        $deck->cards()->attach($request->card_id);

        return response()->json([
            'message' => 'Card added to deck successfully',
            'deck' => $deck->load('cards')
        ]);
    }

    public function removeCard(Deck $deck, Card $salesCallCard)
    {
        try {
            // Only detach this specific card from the deck, don't delete the card
            $deck->cards()->detach($salesCallCard->id);

            // Now delete the card and its containers since it's no longer needed
            $salesCallCard->containers()->delete();
            $salesCallCard->delete();

            return response()->json(['message' => 'Card removed from deck successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to remove card: ' . $e->getMessage()], 500);
        }
    }

    public function removeAllCards(Deck $deck)
    {
        try {
            $cards = $deck->cards;

            $deck->cards()->detach();

            foreach ($cards as $card) {
                $card->containers()->delete();
                $card->delete();
            }

            return response()->json(['message' => 'All cards removed from deck successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to remove cards'], 500);
        }
    }

    private function getDefaultBasePriceMap()
    {
        return [
            // SBY routes (SURABAYA)
            "SBY-MDN-Reefer" => 23000000,  // Terdekat
            "SBY-MDN-Dry" => 14000000,
            "SBY-MKS-Reefer" => 27600000,  // Kedua terdekat
            "SBY-MKS-Dry" => 16800000,
            "SBY-JYP-Reefer" => 32200000,  // Ketiga terdekat
            "SBY-JYP-Dry" => 19600000,
            "SBY-BPN-Reefer" => 36800000,  // Terjauh
            "SBY-BPN-Dry" => 22400000,

            // MDN routes (MEDAN)
            "MDN-MKS-Reefer" => 23000000,  // Terdekat
            "MDN-MKS-Dry" => 14000000,
            "MDN-JYP-Reefer" => 27600000,  // Kedua terdekat
            "MDN-JYP-Dry" => 16800000,
            "MDN-BPN-Reefer" => 32200000,  // Ketiga terdekat
            "MDN-BPN-Dry" => 19600000,
            "MDN-SBY-Reefer" => 36800000,  // Terjauh
            "MDN-SBY-Dry" => 22400000,

            // MKS routes (MAKASSAR)
            "MKS-JYP-Reefer" => 23000000,  // Terdekat
            "MKS-JYP-Dry" => 14000000,
            "MKS-BPN-Reefer" => 27600000,  // Kedua terdekat
            "MKS-BPN-Dry" => 16800000,
            "MKS-SBY-Reefer" => 32200000,  // Ketiga terdekat
            "MKS-SBY-Dry" => 19600000,
            "MKS-MDN-Reefer" => 36800000,  // Terjauh
            "MKS-MDN-Dry" => 22400000,

            // JYP routes (JAYAPURA)
            "JYP-BPN-Reefer" => 23000000,  // Terdekat
            "JYP-BPN-Dry" => 14000000,
            "JYP-SBY-Reefer" => 27600000,  // Kedua terdekat
            "JYP-SBY-Dry" => 16800000,
            "JYP-MDN-Reefer" => 32200000,  // Ketiga terdekat
            "JYP-MDN-Dry" => 19600000,
            "JYP-MKS-Reefer" => 36800000,  // Terjauh
            "JYP-MKS-Dry" => 22400000,

            // BPN routes (BALIKPAPAN)
            "BPN-JYP-Reefer" => 23000000,  // Terdekat
            "BPN-JYP-Dry" => 14000000,
            "BPN-SBY-Reefer" => 27600000,  // Kedua terdekat
            "BPN-SBY-Dry" => 16800000,
            "BPN-MDN-Reefer" => 32200000,  // Ketiga terdekat
            "BPN-MDN-Dry" => 19600000,
            "BPN-MKS-Reefer" => 36800000,  // Terjauh
            "BPN-MKS-Dry" => 22400000
        ];
    }
}
