<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\SimulationLog;
use App\Models\User;
use App\Utilities\RedisService;
use Illuminate\Http\Request;

class SimulationLogController extends Controller
{
    protected $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function index()
    {
        //
    }

    /**
     * Store a simulation log entry via API endpoint
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'room_id' => 'required|exists:rooms,id',
            'arena_bay' => 'required',
            'arena_dock' => 'required',
            'port' => 'required|string',
            'section' => 'required|string',
            'round' => 'required|integer',
            'revenue' => 'required|numeric',
            'penalty' => 'required|numeric',
            'total_revenue' => 'required|numeric',
        ]);

        $simulationLog = $this->createLogEntry($validated);

        return response()->json($simulationLog, 200);
    }

    /**
     * Create a simulation log entry - can be called directly from other controllers
     *
     * @param array $data Log entry data
     * @return SimulationLog The created log entry
     */
    public function createLogEntry(array $data)
    {
        // Prepare log data with proper JSON encoding
        $logData = [
            'user_id' => $data['user_id'],
            'room_id' => $data['room_id'],
            'arena_bay' => is_string($data['arena_bay']) ? $data['arena_bay'] : json_encode($data['arena_bay']),
            'arena_dock' => is_string($data['arena_dock']) ? $data['arena_dock'] : json_encode($data['arena_dock']),
            'port' => $data['port'],
            'section' => $data['section'],
            'round' => $data['round'],
            'revenue' => $data['revenue'],
            'penalty' => $data['penalty'],
            'total_revenue' => $data['total_revenue'],
        ];

        // Create the log entry
        $simulationLog = SimulationLog::create($logData);

        $this->invalidateAllSimulationLogCaches($data['room_id'], $data['user_id'], $data['section'], $data['round']);

        return $simulationLog;
    }

    /**
     * Comprehensive cache invalidation for all possible simulation log cache keys
     */
    private function invalidateAllSimulationLogCaches($roomId, $userId, $section, $round)
    {
        $keysToDelete = [];

        // 1. Basic room-level cache
        $keysToDelete[] = $this->redisService->generateKey('simulation_logs', [
            'room' => $roomId
        ]);

        // 2. Room + User combination
        $keysToDelete[] = $this->redisService->generateKey('simulation_logs', [
            'room' => $roomId,
            'user' => $userId
        ]);

        // 3. Room + User + Section combinations
        $keysToDelete[] = $this->redisService->generateKey('simulation_logs', [
            'room' => $roomId,
            'user' => $userId,
            'section' => $section
        ]);

        $keysToDelete[] = $this->redisService->generateKey('simulation_logs', [
            'room' => $roomId,
            'user' => $userId,
            'section' => 'all'
        ]);

        // 4. Room + User + Section + Round combinations
        $keysToDelete[] = $this->redisService->generateKey('simulation_logs', [
            'room' => $roomId,
            'user' => $userId,
            'section' => $section,
            'round' => $round
        ]);

        $keysToDelete[] = $this->redisService->generateKey('simulation_logs', [
            'room' => $roomId,
            'user' => $userId,
            'section' => $section,
            'round' => 'all'
        ]);

        $keysToDelete[] = $this->redisService->generateKey('simulation_logs', [
            'room' => $roomId,
            'user' => $userId,
            'section' => 'all',
            'round' => $round
        ]);

        $keysToDelete[] = $this->redisService->generateKey('simulation_logs', [
            'room' => $roomId,
            'user' => $userId,
            'section' => 'all',
            'round' => 'all'
        ]);

        // Delete all keys
        foreach (array_unique($keysToDelete) as $key) {
            $this->redisService->delete($key);
        }
    }

    /**
     * Get logs for a specific user in a room with optional Redis caching
     */
    public function getUserLogs(Request $request, $roomId, $userId)
    {
        // Check if caching should be used (default: true)
        $useCache = $request->query('useCache');
        if ($useCache !== null) {
            $useCache = filter_var($useCache, FILTER_VALIDATE_BOOLEAN);
        } else {
            $useCache = true;
        }

        // Validate request parameters
        $validated = $request->validate([
            'section' => 'sometimes|string',
            'round' => 'sometimes|integer',
        ]);

        $section = $validated['section'] ?? null;
        $round = $validated['round'] ?? null;

        $cacheKey = $this->redisService->generateKey('simulation_logs', [
            'room' => $roomId,
            'user' => $userId,
            'section' => $section ?? 'all',
            'round' => $round ?? 'all',
        ]);

        try {
            // Try to get from cache if enabled
            if ($useCache && $this->redisService->has($cacheKey)) {
                return response()->json(
                    $this->redisService->get($cacheKey, null, true),
                    200
                );
            }

            // Buat query base terpisah untuk logs
            $logsQuery = SimulationLog::where('room_id', $roomId)
                ->where('user_id', $userId);

            // Apply additional filters if provided
            if ($section) {
                $logsQuery->where('section', $section);
            }

            if ($round) {
                $logsQuery->where('round', $round);
            }

            // Buat query terpisah untuk count agar tidak interfere dengan query logs
            $countQuery = SimulationLog::where('room_id', $roomId)
                ->where('user_id', $userId);

            if ($section) {
                $countQuery->where('section', $section);
            }

            if ($round) {
                $countQuery->where('round', $round);
            }

            // Get available filters for the UI
            $availableRounds = SimulationLog::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->distinct()
                ->pluck('round')
                ->toArray();

            $availableSections = SimulationLog::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->distinct()
                ->pluck('section')
                ->toArray();

            $totalCount = $countQuery->count();
            $logs = $logsQuery->orderBy('created_at', 'desc')->get();

            $responseData = [
                'logs' => $logs,
                'total' => $totalCount,
                'filters' => [
                    'available_rounds' => $availableRounds,
                    'available_sections' => $availableSections
                ]
            ];

            // Store in cache if enabled (with shorter TTL for user-specific data)
            if ($useCache) {
                $this->redisService->set($cacheKey, $responseData, 1800, true); // 30 minutes
            }

            return response()->json($responseData, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch user logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show(SimulationLog $simulationLog)
    {
        return response()->json($simulationLog);
    }

    public function update(Request $request, SimulationLog $simulationLog)
    {
        //
    }

    public function destroy(SimulationLog $simulationLog)
    {
        //
    }
}
