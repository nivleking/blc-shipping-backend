<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Container;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ContainerController extends Controller
{
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

    // public function getContainersByRoom($roomId)
    // {
    //     $room = Room::find($roomId);
    //     $deckId = $room->deck_id;
    //     $containers = Container::where('deck_id', $deckId)
    //         ->with('card:id,destination,type,quantity')
    //         ->get();

    //     return response()->json($containers);
    // }

    public function getContainersByRoom($roomId)
    {
        $cacheKey = "containers:room:{$roomId}";

        try {
            if (Redis::exists($cacheKey)) {
                $cachedData = Redis::get($cacheKey);
                return response()->json(json_decode($cachedData), 200);
            }

            // Step 2: Cache miss - fetch data from database
            $room = Room::find($roomId);
            if (!$room) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            $containers = Container::where('deck_id', $room->deck_id)->get();

            // Step 3: Store the result in Redis (with 1-hour expiration)
            // Containers change rarely, so we can set a longer TTL
            Redis::setex($cacheKey, 3600, json_encode($containers));

            // Step 4: Return the data to the client
            return response()->json($containers, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch containers',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
