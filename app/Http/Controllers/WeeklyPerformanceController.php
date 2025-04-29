<?php

namespace App\Http\Controllers;

use App\Models\WeeklyPerformance;
use App\Models\ShipBay;
use App\Models\CardTemporary;
use App\Models\Room;
use App\Models\CapacityUptake;
use App\Models\Container;
use Illuminate\Http\Request;

class WeeklyPerformanceController extends Controller
{
    /**
     * Get financial summary including estimated penalties
     */
    public function getFinancialSummary($roomId, $userId)
    {
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return response()->json([
                'error' => 'Ship bay not found'
            ], 404);
        }

        $room = Room::find($roomId);
        $moveCost = $room ? $room->move_cost : 0;

        // Get capacity uptake for current round to calculate revenue from accepted cards
        $currentRound = $shipBay->current_round;
        $capacityUptake = CapacityUptake::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('week', $currentRound)
            ->first();

        // Get the controller instance to calculate penalties
        $roomController = new RoomController();

        // Calculate unrolled penalties
        $unrolledPenaltyData = $roomController->calculateUnrolledPenalties($room, $userId, $shipBay, $shipBay->current_round);
        $unrolledPenalty = $unrolledPenaltyData['penalty'] ?? 0;
        $unrolledContainerCounts = $unrolledPenaltyData['container_counts'] ?? [];

        // Calculate dock warehouse penalties
        $dockWarehouseData = $roomController->calculateDockWarehouse($room, $userId, $shipBay->current_round + 1);
        $dockWarehousePenalty = $dockWarehouseData['penalty'] ?? 0;
        $dockWarehouseContainerCounts = $dockWarehouseData['container_counts'] ?? [];

        // Calculate restowage penalties
        $restowageData = $roomController->calculateRestowagePenalties($room, $userId);
        $restowagePenalty = $restowageData['penalty'] ?? 0;
        $restowageContainerCount = $restowageData['container_count'] ?? 0;
        $restowageMoves = $restowageData['restowage_moves'] ?? 0;

        // Calculate total moves cost
        $totalMoves = $shipBay->discharge_moves + $shipBay->load_moves;
        $movesPenalty = $totalMoves * $moveCost;

        // Calculate total penalties
        $totalPenalty = $movesPenalty + $dockWarehousePenalty + $unrolledPenalty + $restowagePenalty;

        // Get revenue from capacity uptake accepted cards
        $revenue = 0;
        if ($capacityUptake && is_array($capacityUptake->accepted_cards)) {
            foreach ($capacityUptake->accepted_cards as $card) {
                if (isset($card['revenue'])) {
                    $revenue += (int)$card['revenue'];
                }
            }
        } else {
            // Fallback to ShipBay revenue if capacity uptake data isn't available
            $revenue = $shipBay->revenue ?? 0;
        }

        $finalRevenue = $revenue - $totalPenalty;

        // Get cost rates from room model
        $dockWarehouseCosts = $room ? $room->dock_warehouse_costs : [];
        $restowageCost = $room ? $room->restowage_cost : 0;

        // Get unrolled cost rates from market intelligence
        $unrolledCostRates = $unrolledPenaltyData['rates'] ?? [];

        return response()->json([
            'dock_warehouse_costs' => $dockWarehouseCosts,
            'restowage_cost' => $restowageCost,
            'unrolled_cost_rates' => $unrolledCostRates,
            'revenue' => $revenue,
            'load_moves' => $shipBay->load_moves,
            'discharge_moves' => $shipBay->discharge_moves,
            'total_moves' => $totalMoves,
            'move_cost' => $moveCost,
            'moves_penalty' => $movesPenalty,
            'dock_warehouse_penalty' => $dockWarehousePenalty,
            'unrolled_penalty' => $unrolledPenalty,
            'restowage_penalty' => $restowagePenalty,
            'total_penalty' => $totalPenalty,
            'final_revenue' => $finalRevenue,
            // Add container counts
            'unrolled_container_counts' => $unrolledContainerCounts,
            'dock_warehouse_container_counts' => $dockWarehouseContainerCounts,
            'restowage_container_count' => $restowageContainerCount,
            'restowage_moves' => $restowageMoves
        ]);
    }

    public function getWeeklyPerformance($roomId, $userId, $week = null)
    {
        // If week is not provided, get the current week from ShipBay
        if (!$week) {
            $shipBay = ShipBay::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->first();

            if ($shipBay) {
                $week = $shipBay->current_round;
            } else {
                $week = 1;
            }
        }

        // Find weekly performance record
        $performance = WeeklyPerformance::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('week', $week)
            ->first();

        // if (!$performance) {
        //     // If no record found, create it using financial summary data
        //     $room = Room::find($roomId);
        //     if ($room) {
        //         $roomController = new RoomController();
        //         $performance = $roomController->finalizeWeeklyPerformance($room, $userId, $week);
        //     }
        // }

        // Get ship bay for additional data
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        // Get financial summary from ShipBayController for consistent data
        $shipBayController = new ShipBayController();
        $financialSummary = null;

        if ($shipBay) {
            $financialSummaryResponse = $shipBayController->getFinancialSummary($roomId, $userId);
            $financialSummary = json_decode($financialSummaryResponse->getContent(), true);
        }

        // Enhance response with financial data
        $responseData = $performance ? $performance->toArray() : [];
        if ($financialSummary) {
            $responseData['financial_summary'] = $financialSummary;
        }

        return response()->json([
            'data' => $responseData
        ], 200);
    }

    public function getAllWeeklyPerformance($roomId, $userId)
    {
        // Get all weeks' performance for this user/room
        $performances = WeeklyPerformance::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->orderBy('week')
            ->get();

        // Calculate cumulative revenue for trend analysis
        $cumulativeRevenue = 0;
        foreach ($performances as &$performance) {
            $cumulativeRevenue += $performance->net_result;
            $performance->cumulative_revenue = $cumulativeRevenue;
        }

        return response()->json([
            'data' => $performances
        ], 200);
    }

    private function calculateWeeklyPerformance($roomId, $userId, $week)
    {
        // Get ship bay data
        $shipBay = ShipBay::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$shipBay) {
            return null;
        }

        // Get capacity uptake for this week
        $capacityUptake = CapacityUptake::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('week', $week)
            ->first();

        // Get room for move cost
        $room = Room::find($roomId);

        // Get loaded containers from ShipBay arena
        $containerIds = $this->extractContainerIdsFromArena(json_decode($shipBay->arena, true));
        $containers = Container::whereIn('id', $containerIds)->get();

        $dryContainersLoaded = $containers->where('type', 'dry')->count();
        $reeferContainersLoaded = $containers->where('type', 'reefer')->count();

        // Calculate containers not loaded (from capacity uptake accepted cards)
        $dryContainersNotLoaded = $capacityUptake ? $capacityUptake->dry_containers_accepted - $dryContainersLoaded : 0;
        $reeferContainersNotLoaded = $capacityUptake ? $capacityUptake->reefer_containers_accepted - $reeferContainersLoaded : 0;

        // If containers not loaded is negative (should not happen normally), set to 0
        $dryContainersNotLoaded = max(0, $dryContainersNotLoaded);
        $reeferContainersNotLoaded = max(0, $reeferContainersNotLoaded);

        // Process rejected cards to determine commitment status
        $committedDryContainersNotLoaded = 0;
        $committedReeferContainersNotLoaded = 0;
        $nonCommittedDryContainersNotLoaded = 0;
        $nonCommittedReeferContainersNotLoaded = 0;

        if ($capacityUptake && isset($capacityUptake->rejected_cards) && is_array($capacityUptake->rejected_cards)) {
            foreach ($capacityUptake->rejected_cards as $card) {
                $isCommitted = strtolower($card['priority']) === 'committed';
                $isDry = strtolower($card['type']) === 'dry';
                $quantity = $card['quantity'] ?? ($card['totalContainers'] ?? 1);

                if ($isCommitted) {
                    if ($isDry) {
                        $committedDryContainersNotLoaded += $quantity;
                    } else {
                        $committedReeferContainersNotLoaded += $quantity;
                    }
                } else {
                    if ($isDry) {
                        $nonCommittedDryContainersNotLoaded += $quantity;
                    } else {
                        $nonCommittedReeferContainersNotLoaded += $quantity;
                    }
                }
            }
        }

        // Get move costs
        $moveCost = $room ? $room->move_cost : 0;
        $moveCosts = ($shipBay->discharge_moves + $shipBay->load_moves) * $moveCost;

        // Get extra moves penalty and data directly from ship bay
        // $extraMovesPenalty = $shipBay->extra_moves_penalty ?? 0;
        // $longCraneMoves = $shipBay->long_crane_moves ?? 0;
        // $extraMovesOnLongCrane = $shipBay->extra_moves_on_long_crane ?? 0;
        // $idealCraneSplit = $room->ideal_crane_split ?? 2;

        // Calculate net result
        $revenue = $shipBay->revenue;
        $netResult = $revenue - $moveCosts;

        // Create or update weekly performance record
        $performance = WeeklyPerformance::updateOrCreate(
            [
                'room_id' => $roomId,
                'user_id' => $userId,
                'week' => $week
            ],
            [
                'dry_containers_loaded' => $dryContainersLoaded,
                'reefer_containers_loaded' => $reeferContainersLoaded,
                'dry_containers_not_loaded' => $dryContainersNotLoaded,
                'reefer_containers_not_loaded' => $reeferContainersNotLoaded,
                'committed_dry_containers_not_loaded' => $committedDryContainersNotLoaded,
                'committed_reefer_containers_not_loaded' => $committedReeferContainersNotLoaded,
                'non_committed_dry_containers_not_loaded' => $nonCommittedDryContainersNotLoaded,
                'non_committed_reefer_containers_not_loaded' => $nonCommittedReeferContainersNotLoaded,
                'revenue' => $revenue,
                'move_costs' => $moveCosts,
                'net_result' => $netResult,
                'discharge_moves' => $shipBay->discharge_moves,
                'load_moves' => $shipBay->load_moves,
                // 'extra_moves_penalty' => $extraMovesPenalty,
                // 'long_crane_moves' => $longCraneMoves,
                // 'extra_moves_on_long_crane' => $extraMovesOnLongCrane,
                // 'ideal_crane_split' => $idealCraneSplit
            ]
        );

        return $performance;
    }

    /**
     * Extract container IDs from the arena data
     */
    private function extractContainerIdsFromArena($arena)
    {
        $containerIds = [];

        if (is_array($arena)) {
            foreach ($arena as $item) {
                if (isset($item['id'])) {
                    $containerIds[] = $item['id'];
                }
            }
        }

        return $containerIds;
    }

    /**
     * Update weekly performance data when a round is completed
     */
    public function updateWeeklyPerformance(Request $request, $roomId, $userId, $week)
    {
        // Force recalculation of performance data
        $performance = $this->calculateWeeklyPerformance($roomId, $userId, $week);

        return response()->json([
            'message' => 'Weekly performance data updated successfully',
            'data' => $performance
        ], 200);
    }
}
