<?php

namespace App\Http\Controllers;

use App\Models\CardTemporary;
use App\Models\Container;
use App\Models\Room;
use App\Models\ShipBay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardTemporaryController extends Controller
{
    public function acceptCardTemporary(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'card_temporary_id' => 'required|exists:cards,id',
            'round' => 'required|integer|min:1',
        ]);

        $cardTemporary = CardTemporary::where('card_id', $validated['card_temporary_id'])
            ->where('room_id', $validated['room_id'])
            ->first();

        if (!$cardTemporary) {
            return response()->json(['error' => 'Card temporary not found'], 404);
        }

        // Get all container IDs for this card
        $containers = Container::where('card_id', $cardTemporary->card_id)
            ->where('deck_id', $cardTemporary->deck_id)
            ->pluck('id')
            ->toArray();

        $cardTemporary->status = 'accepted';
        $cardTemporary->round = $validated['round'];
        $cardTemporary->unfulfilled_containers = $containers;
        $cardTemporary->revenue_granted = false;
        $cardTemporary->save();

        return response()->json([
            'message' => 'Sales call card accepted.',
            'unfulfilled_containers' => $containers
        ]);
    }

    public function rejectCardTemporary(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'card_temporary_id' => 'required|exists:cards,id',
            'round' => 'required|integer|min:1',
        ]);

        $cardTemporary = CardTemporary::where('card_id', $validated['card_temporary_id'])
            ->where('room_id', $validated['room_id'])
            ->first();

        $cardTemporary->status = 'rejected';
        $cardTemporary->round = $validated['round'];
        $cardTemporary->save();

        return response()->json(['message' => 'Sales call card rejected.']);
    }

    public function getUnfulfilledContainers($roomId, $userId)
    {
        $cardTemporaries = CardTemporary::where([
            'room_id' => $roomId,
            'user_id' => $userId,
            'status' => 'accepted',
        ])
            ->whereNotNull('unfulfilled_containers')
            ->where('unfulfilled_containers', '!=', '[]')
            ->get();

        $unfulfilledContainers = [];

        foreach ($cardTemporaries as $card) {
            $unfulfilledContainers[$card->card_id] = $card->unfulfilled_containers;
        }

        return response()->json($unfulfilledContainers);
    }

    public function getCardTemporaries($roomId, $userId)
    {
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        $currentRound = $shipBay->current_round;

        // Use a direct join instead of relationship loading
        $cardTemporaries = CardTemporary::select(
            'card_temporaries.*',
            'cards.type',
            'cards.priority',
            'cards.origin',
            'cards.destination',
            'cards.quantity',
            'cards.revenue'
        )
            ->join('cards', function ($join) {
                $join->on('cards.id', '=', 'card_temporaries.card_id')
                    ->on('cards.deck_id', '=', 'card_temporaries.deck_id');
            })
            ->where([
                'card_temporaries.room_id' => $roomId,
                'card_temporaries.user_id' => $userId,
                'card_temporaries.status' => 'selected',
            ])
            ->where(function ($query) use ($currentRound) {
                $query->where('card_temporaries.round', $currentRound)
                    ->orWhere('card_temporaries.is_backlog', true);
            })
            ->orderByRaw('card_temporaries.is_backlog DESC')
            ->orderByRaw("CASE WHEN cards.priority = 'Committed' THEN 1 ELSE 2 END")
            // ->orderByRaw("CAST(card_temporaries.card_id AS UNSIGNED) ASC")
            ->get();

        // Format the result to match what the frontend expects
        $formattedResult = $cardTemporaries->map(function ($temp) {
            // Create a card property with the joined data
            $temp->card = [
                'id' => $temp->card_id,
                'deck_id' => $temp->deck_id,
                'type' => $temp->type,
                'priority' => $temp->priority,
                'origin' => $temp->origin,
                'destination' => $temp->destination,
                'quantity' => $temp->quantity,
                'revenue' => $temp->revenue
            ];

            // Remove duplicated attributes
            unset($temp->type);
            unset($temp->priority);
            unset($temp->origin);
            unset($temp->destination);
            unset($temp->quantity);
            unset($temp->revenue);

            return $temp;
        });

        $room = Room::find($roomId);

        // Get first card limit cards from formattedResult
        $cardsLimit = $room->cards_limit_per_round;
        if ($formattedResult->count() > $cardsLimit) {
            $formattedResult = $formattedResult->take($cardsLimit);
        }

        return response()->json([
            "cards" => $formattedResult,
        ]);
    }

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
