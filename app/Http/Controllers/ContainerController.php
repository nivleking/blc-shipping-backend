<?php

namespace App\Http\Controllers;

use App\Models\Container;
use Illuminate\Http\Request;

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
    public function show(Container $container)
    {
        // return $container->load(['card:id,destination,type,quantity']);
        return response()->json([
            'container' => $container,
            'card' => $container->card
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

    // public function getBatch(Request $request)
    // {
    //     $request->validate([
    //         'containerIds' => 'required|array',
    //         'containerIds.*' => 'exists:containers,id'
    //     ]);

    //     $containers = Container::whereIn('id', $request->containerIds)
    //         ->with('card:id,destination,type,priority,origin,quantity')
    //         ->get();

    //     return response()->json($containers);
    // }
}
