<?php

namespace App\Http\Controllers;

use App\Models\CardTemporary;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardTemporaryController extends Controller
{
    /**
     * Create multiple card temporaries in a batch
     */
    public function batchCreate(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'user_id' => 'required|exists:users,id',
            'round' => 'required|integer',
            'cards' => 'required|array',
            'cards.*.deck_id' => 'required|exists:decks,id',
            'cards.*.card_id' => 'required|exists:cards,id',
            'cards.*.status' => 'sometimes|string|in:selected,accepted,rejected',
        ]);

        // Begin transaction for better performance and data integrity
        DB::beginTransaction();

        try {
            $roomId = $validated['room_id'];
            $userId = $validated['user_id'];
            $round = $validated['round'];

            CardTemporary::where([
                'user_id' => $userId,
                'room_id' => $roomId,
            ])->delete();

            $cardTemporaries = [];

            $room = Room::findOrFail($roomId);
            $cardsLimitPerRound = $room->cards_limit_per_round;
            $roomPrefix = "R{$room->id}-"; // This is the prefix for room-specific cards

            // Sort the cards by card_id in ascending order
            $sortedCards = collect($request->cards)
                ->filter(function ($card) use ($roomPrefix) {
                    return !str_starts_with($card['card_id'], $roomPrefix);
                })
                ->sortBy(function ($card) {
                    return (int)$card['card_id'];
                })
                ->values()
                ->all();

            // Process each card
            foreach ($sortedCards as $index => $card) {
                $status = $card['status'] ?? 'selected';

                // Determine the round value:
                // - For the first N cards (where N is cards_limit_per_round), set to the current round
                // - For the rest, set to null (or 0 if you prefer)
                $cardRound = $index < $cardsLimitPerRound ? $round : null;

                // Check if card temporary already exists
                $existing = CardTemporary::where([
                    'user_id' => $userId,
                    'room_id' => $roomId,
                    'card_id' => $card['card_id'],
                    'deck_id' => $card['deck_id'],
                ])->first();

                if (!$existing) {
                    $cardTemp = CardTemporary::create([
                        'user_id' => $userId,
                        'room_id' => $roomId,
                        'card_id' => $card['card_id'],
                        'deck_id' => $card['deck_id'],
                        'status' => $status,
                        'round' => $cardRound
                    ]);

                    $cardTemporaries[] = $cardTemp;
                }
            }

            DB::commit();

            return response()->json([
                'message' => count($cardTemporaries) . ' card temporaries created successfully',
                'card_temporaries' => $cardTemporaries
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create card temporaries',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
