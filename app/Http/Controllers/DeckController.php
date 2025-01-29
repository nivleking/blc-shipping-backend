<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Deck;
use App\Models\SalesCallCard;
use Illuminate\Http\Request;

class DeckController extends Controller
{
    public function index()
    {
        $decks = Deck::all();
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

    public function show(Deck $deck)
    {
        return response()->json($deck->load('cards'), 200);
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
        // $deck->cards()->delete();
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

    public function removeCard(Deck $deck, Card $card)
    {
        $deck->cards()->detach($card->id);
        return response()->json(['message' => 'Card removed from deck']);
    }
}
