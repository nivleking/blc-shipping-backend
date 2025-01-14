<?php

namespace App\Http\Controllers;

use App\Models\SalesCallCardDeck;
use App\Models\SalesCallCard;
use Illuminate\Http\Request;

class SalesCallCardDeckController extends Controller
{
    public function index()
    {
        $decks = SalesCallCardDeck::all();
        return response()->json($decks);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $deck = SalesCallCardDeck::create($validated);
        return response()->json($deck, 201);
    }

    public function show(SalesCallCardDeck $deck)
    {
        return response()->json($deck->load('cards'), 200);
    }

    public function update(Request $request, SalesCallCardDeck $deck)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
        ]);

        $deck->update($validated);
        return response()->json($deck, 200);
    }

    public function destroy(SalesCallCardDeck $deck)
    {
        $deck->delete();
        return response()->json(null, 204);
    }

    public function showByDeck(SalesCallCardDeck $deck)
    {
        return response()->json($deck->load('cards'), 200);
    }

    public function getOrigins(SalesCallCardDeck $deck)
    {
        $origins = $deck->cards()->pluck('origin')->unique();
        return response()->json($origins, 200);
    }

    public function addSalesCallCard(Request $request, SalesCallCardDeck $deck)
    {
        $deck->cards()->attach($request->card_id);
        return response()->json(['message' => 'Card added to deck']);
    }

    public function removeSalesCallCard(SalesCallCardDeck $deck, SalesCallCard $card)
    {
        $deck->cards()->detach($card->id);
        return response()->json(['message' => 'Card removed from deck']);
    }
}
