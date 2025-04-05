<?php

namespace App\Http\Controllers;

use App\Models\CardTemporary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardTemporaryController extends Controller
{
    /**
     * Create multiple card temporaries in a batch
     */
    public function batchCreate(Request $request)
    {
        $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'user_id' => 'required|exists:users,id',
            'cards' => 'required|array',
            'cards.*.card_id' => 'required|exists:cards,id',
            'cards.*.status' => 'sometimes|string|in:selected,accepted,rejected',
            'cards.*.round' => 'sometimes|integer',
        ]);

        // Begin transaction for better performance and data integrity
        DB::beginTransaction();

        try {
            $cardTemporaries = [];
            $round = $request->input('round', 1);

            foreach ($request->cards as $card) {
                $status = $card['status'] ?? 'selected';
                $cardRound = $card['round'] ?? $round;

                // Check if card temporary already exists
                $existing = CardTemporary::where([
                    'user_id' => $request->user_id,
                    'room_id' => $request->room_id,
                    'card_id' => $card['card_id'],
                    'deck_id' => $card['deck_id'],
                ])->first();

                if (!$existing) {
                    $cardTemp = CardTemporary::create([
                        'user_id' => $request->user_id,
                        'room_id' => $request->room_id,
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
