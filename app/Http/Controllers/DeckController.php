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
        return response()->json($deck, 201);
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
}
