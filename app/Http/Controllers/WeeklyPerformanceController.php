<?php

namespace App\Http\Controllers;

use App\Models\WeeklyPerformance;
use App\Models\ShipBay;
use App\Models\CardTemporary;
use App\Models\Room;
use Illuminate\Http\Request;

class WeeklyPerformanceController extends Controller
{
    public function getWeeklyPerformance($roomId, $userId)
    {
        $performance = WeeklyPerformance::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$performance) {
            // Generate default data
            $defaultData = $this->generateDefaultData($roomId, $userId);

            return response()->json([
                'message' => 'Weekly performance data retrieved successfully',
                'data' => $defaultData
            ], 200);
        }

        return response()->json([
            'message' => 'Weekly performance data retrieved successfully',
            'data' => $performance->data
        ], 200);
    }

    public function storeWeeklyPerformance(Request $request, $roomId, $userId)
    {
        $request->validate([
            'data' => 'required|array',
        ]);

        // Create or update the weekly performance data
        $performance = WeeklyPerformance::updateOrCreate(
            ['room_id' => $roomId, 'user_id' => $userId],
            ['data' => $request->data]
        );

        return response()->json([
            'message' => 'Weekly performance data saved successfully',
            'data' => $performance->data
        ], 200);
    }

    public function updateWeek(Request $request, $roomId, $userId, $weekNumber)
    {
        $request->validate([
            'weekData' => 'required|array',
        ]);

        $performance = WeeklyPerformance::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$performance) {
            return response()->json([
                'message' => 'Weekly performance data not found',
            ], 404);
        }

        $data = $performance->data;

        // Find and update the specific week
        foreach ($data['weeks'] as $key => $week) {
            if ($week['weekNumber'] == $weekNumber) {
                $data['weeks'][$key] = array_merge($week, $request->weekData);
                break;
            }
        }

        // Recalculate totals
        $data['totalRevenue'] = collect($data['weeks'])->sum('revenue');
        $data['totalPenalties'] = collect($data['weeks'])->sum('totalPenalty');

        $performance->data = $data;
        $performance->save();

        return response()->json([
            'message' => 'Weekly performance data updated successfully',
            'data' => $performance->data
        ], 200);
    }

    /**
     * Generate default data structure for weekly performance
     */
    private function generateDefaultData($roomId, $userId)
    {
        // First get the total number of rounds from Room
        $totalRounds = Room::find($roomId)->total_rounds ?? 4;
        $currentRound = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first()
            ->current_round ?? 1;

        // Calculate rolled containers for each week
        $weeks = [];
        $totalRevenue = 0;

        for ($week = 1; $week <= $totalRounds; $week++) {
            // Only process data for completed weeks
            if ($week > $currentRound) {
                $weeks[] = [
                    'weekNumber' => $week,
                    'rolledCommittedDry' => null,
                    'rolledCommittedReefer' => null,
                    'rolledNonCommittedDry' => null,
                    'rolledNonCommittedReefer' => null,
                    'revenue' => null,
                ];
                continue;
            }

            // Get the rejected cards for this user/room/week
            $rejectedCards = CardTemporary::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->where('status', 'rejected')
                ->where('round', $week)
                ->with('card')
                ->get();

            // Count rolled containers by type
            $rolledCommittedDry = 0;
            $rolledCommittedReefer = 0;
            $rolledNonCommittedDry = 0;
            $rolledNonCommittedReefer = 0;

            foreach ($rejectedCards as $cardTemp) {
                if (!$cardTemp->card) continue;

                $card = $cardTemp->card;
                $quantity = $card->quantity ?? 1;
                $type = strtolower($card->type ?? 'dry');
                $isCommitted = $card->is_committed ?? false;

                if ($type === 'dry') {
                    if ($isCommitted) {
                        $rolledCommittedDry += $quantity;
                    } else {
                        $rolledNonCommittedDry += $quantity;
                    }
                } else if ($type === 'reefer') {
                    if ($isCommitted) {
                        $rolledCommittedReefer += $quantity;
                    } else {
                        $rolledNonCommittedReefer += $quantity;
                    }
                }
            }

            // Get revenue for this week
            $shipBay = ShipBay::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->where('current_round', $week)
                ->first();

            $revenue = $shipBay ? $shipBay->revenue : 0;
            $totalRevenue += $revenue;

            $weeks[] = [
                'weekNumber' => $week,
                'rolledCommittedDry' => $rolledCommittedDry,
                'rolledCommittedReefer' => $rolledCommittedReefer,
                'rolledNonCommittedDry' => $rolledNonCommittedDry,
                'rolledNonCommittedReefer' => $rolledNonCommittedReefer,
                'revenue' => $revenue,
            ];
        }

        return [
            'weeks' => $weeks,
            'totalRevenue' => $totalRevenue,
            'totalPenalties' => 0,
            'penaltyMatrix' => [
                'dry' => [
                    'committed' => 0,
                    'nonCommitted' => 0,
                ],
                'reefer' => [
                    'committed' => 0,
                    'nonCommitted' => 0,
                ],
                'additionalRolledPenalty' => 0,
                'restowPenalty' => 0,
                'longCranePenalty' => 0,
            ]
        ];
    }
}
