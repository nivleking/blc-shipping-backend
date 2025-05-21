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
    /**
     * Get all card temporaries for a room and deck
     */
    public function getAllCardTemporaries($roomId, $deckId)
    {
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
            ->where('card_temporaries.room_id', $roomId)
            ->where('card_temporaries.deck_id', $deckId)
            ->get()
            ->map(function ($temp) {
                $temp->card = [
                    'id' => $temp->card_id,
                    'type' => $temp->type,
                    'priority' => $temp->priority,
                    'origin' => $temp->origin,
                    'destination' => $temp->destination,
                    'quantity' => $temp->quantity,
                    'revenue' => $temp->revenue,
                ];

                unset($temp->type);
                unset($temp->priority);
                unset($temp->origin);
                unset($temp->destination);
                unset($temp->quantity);
                unset($temp->revenue);

                return $temp;
            });

        return response()->json([
            "cards" => $cardTemporaries,
        ]);
    }

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
        // Get ShipBay data for current round and port
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json([
                "error" => "Ship bay not found for this user and room"
            ], 404);
        }

        $currentRound = $shipBay->current_round;

        // Get Room data for configuration settings
        $room = Room::find($roomId);
        if (!$room) {
            return response()->json([
                "error" => "Room not found"
            ], 404);
        }

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
            ->orderByRaw("LENGTH(card_temporaries.card_id) ASC") // Sort by card ID length first
            // THIS IS FOR PRODUCTION
            ->orderByRaw("REGEXP_REPLACE(card_temporaries.card_id, '^(\\d)(\\d+)(\\d{2})$', '\\1\\2') + 0") // Then by port+week
            // THIS IS FOR LOCAL
            // ->orderByRaw("SUBSTRING_INDEX(card_temporaries.card_id, RIGHT(card_temporaries.card_id, 2), 1) + 0") // Then by port+week
            ->orderByRaw("RIGHT(card_temporaries.card_id, 2) + 0") // Then by card number
            ->get();

        // Format the result to match what the frontend expects
        $formattedResult = $cardTemporaries->map(function ($temp) {
            $temp->card = [
                'id' => $temp->card_id,
                'type' => $temp->type,
                'priority' => $temp->priority,
                'origin' => $temp->origin,
                'destination' => $temp->destination,
                'quantity' => $temp->quantity,
                'revenue' => $temp->revenue,
            ];

            unset($temp->type);
            unset($temp->priority);
            unset($temp->origin);
            unset($temp->destination);
            unset($temp->quantity);
            unset($temp->revenue);

            return $temp;
        });

        // Apply card limit (keeping existing logic)
        $cardsLimit = $room->cards_limit_per_round;
        if ($formattedResult->count() > $cardsLimit) {
            $formattedResult = $formattedResult->take($cardsLimit);
        }

        // Check if card limit is exceeded
        $isLimitExceeded = $shipBay->processed_cards >= $room->cards_must_process_per_round ||
            $shipBay->current_round_cards >= $room->cards_limit_per_round;

        $containers = Container::whereIn('card_id', $formattedResult->pluck('card_id'))
            ->whereIn('deck_id', $formattedResult->pluck('deck_id'))
            ->get();

        // Return enhanced response with all required data
        return response()->json([
            "cards" => $formattedResult,
            "deck_id" => $room->deck_id,
            "move_cost" => $room->move_cost,
            "cards_must_process_per_round" => $room->cards_must_process_per_round,
            "cards_limit_per_round" => $cardsLimit,
            "port" => $shipBay->port,
            "is_limit_exceeded" => $isLimitExceeded,
            "current_round" => $currentRound,
            "total_rounds" => $room->total_rounds,
            "containers" => $containers,
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

            // For the batchCreate method
            $sortedCards = collect($request->cards)
                ->filter(function ($card) use ($roomPrefix) {
                    return !str_starts_with($card['card_id'], $roomPrefix);
                })
                ->sortBy(function ($card) {
                    $cardId = $card['card_id'];

                    // First sort by number of digits (shorter IDs come first)
                    $digitCount = strlen($cardId);

                    // Then extract components for more detailed sorting
                    if (preg_match('/^(\d)(\d+)(\d{2})$/', $cardId, $matches)) {
                        [, $port, $week, $cardNum] = $matches;

                        // Create a sortable key: digit_count + port + padded_week + card_number
                        return $digitCount . '_' . $port . str_pad($week, 5, '0', STR_PAD_LEFT) . $cardNum;
                    }

                    // Fallback for any cards that don't match the pattern
                    return $digitCount . '_' . $cardId;
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

    /**
     * Create multiple card temporaries for multiple users in a single batch
     */
    public function batchCreateMultiUser(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'batches' => 'required|array',
            'batches.*.user_id' => 'required|exists:users,id',
            'batches.*.round' => 'required|integer',
            'batches.*.cards' => 'required|array',
            'batches.*.cards.*.deck_id' => 'required|exists:decks,id',
            'batches.*.cards.*.card_id' => 'required|exists:cards,id',
            'batches.*.cards.*.status' => 'sometimes|string|in:selected,accepted,rejected',
        ]);

        // Begin transaction for better performance and data integrity
        DB::beginTransaction();

        try {
            $roomId = $validated['room_id'];
            $room = Room::findOrFail($roomId);
            $cardsLimitPerRound = $room->cards_limit_per_round;
            $roomPrefix = "R{$room->id}-";

            $results = [];

            // Process each user's batch
            foreach ($validated['batches'] as $batch) {
                $userId = $batch['user_id'];
                $round = $batch['round'];

                // Delete existing card temporaries for this user
                CardTemporary::where([
                    'user_id' => $userId,
                    'room_id' => $roomId,
                ])->delete();

                $sortedCards = collect($batch['cards'])
                    ->filter(function ($card) use ($roomPrefix) {
                        return !str_starts_with($card['card_id'], $roomPrefix);
                    })
                    ->sortBy(function ($card) {
                        $cardId = $card['card_id'];

                        // First sort by number of digits (shorter IDs come first)
                        $digitCount = strlen($cardId);

                        // Then extract components for more detailed sorting
                        if (preg_match('/^(\d)(\d+)(\d{2})$/', $cardId, $matches)) {
                            [, $port, $week, $cardNum] = $matches;

                            // Create a sortable key: digit_count + port + padded_week + card_number
                            return $digitCount . '_' . $port . str_pad($week, 5, '0', STR_PAD_LEFT) . $cardNum;
                        }

                        // Fallback for any cards that don't match the pattern
                        return $digitCount . '_' . $cardId;
                    })
                    ->values()
                    ->all();

                $userCardTemporaries = [];

                // Process each card
                foreach ($sortedCards as $index => $card) {
                    $status = $card['status'] ?? 'selected';
                    $cardRound = $index < $cardsLimitPerRound ? $round : null;

                    $cardTemp = CardTemporary::create([
                        'user_id' => $userId,
                        'room_id' => $roomId,
                        'card_id' => $card['card_id'],
                        'deck_id' => $card['deck_id'],
                        'status' => $status,
                        'round' => $cardRound
                    ]);

                    $userCardTemporaries[] = $cardTemp;
                }

                $results[$userId] = [
                    'count' => count($userCardTemporaries),
                    'port' => $batch['port'] ?? null
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Card temporaries created successfully for all users',
                'results' => $results
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
