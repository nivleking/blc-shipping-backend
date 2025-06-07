<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Container;
use App\Models\Room;
use App\Utilities\RedisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ContainerController extends Controller
{
    protected $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Container::with('card')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($roomId, $container)
    {
        $containerModel = Container::findOrFail($container);

        return response()->json([
            'container' => $containerModel,
            'card' => $containerModel->card
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Container $container)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Container $container)
    {
        //
    }

    public function getContainerDestinations(Request $request)
    {
        $validated = $request->validate([
            'containerIds' => 'required|array',
            'containerIds.*' => 'exists:containers,id',
            'deckId' => 'required|exists:decks,id'
        ]);

        $containerDestinations = [];
        $containers = Container::whereIn('id', $request->containerIds)
            ->where('deck_id', $request->deckId)
            // ->with('card:id,destination,type,quantity')
            ->get();

        foreach ($containers as $container) {
            if ($container->card) {
                $containerDestinations[$container->id] = $container->card->destination;
            }
        }

        return response()->json($containerDestinations);
    }

    public function getContainersByRoom(Request $request, $roomId)
    {
        $useCache = $request->query('useCache');
        if ($useCache !== null) {
            $useCache = filter_var($useCache, FILTER_VALIDATE_BOOLEAN);
        } else {
            $useCache = true;
        }

        $cacheKey = $this->redisService->generateKey('containers', ['room' => $roomId]);

        try {
            if ($useCache && $this->redisService->has($cacheKey)) {
                $cachedData = $this->redisService->get($cacheKey, null, false);
                return response()->json($cachedData, 200);
            }

            $room = Room::find($roomId);
            if (!$room) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            $containers = Container::where('deck_id', $room->deck_id)->get();

            if ($useCache) {
                $this->redisService->set($cacheKey, $containers, 3600, false);
            }

            return response()->json($containers, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch containers',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
