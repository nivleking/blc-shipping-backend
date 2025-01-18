<?php

use App\Http\Controllers\ContainerController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\ShipBayController;
use App\Http\Controllers\ShipDockController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json(['message' => 'Hello World!']);
});

Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    return "Cache is cleared";
});

Route::get('all-users', [UserController::class, 'getAllUsers']);
Route::get('all-admins', [UserController::class, 'getAllAdmins']);

Route::get('moveDragMe', function () {
    return response()->json(['message' => 'Drag me to the right place!']);
});

// Token
Route::post('users/login', [UserController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('users/refresh-token', [UserController::class, 'refreshToken']);
});
Route::middleware('auth:sanctum', 'ability:access-api')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// User Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('users/register', [UserController::class, 'register'])->middleware('admin');
    Route::post('users/logout', [UserController::class, 'logout']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('admin');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('admin');
});

// Admin - Deck Routes
Route::get('decks', [DeckController::class, 'index']);
Route::post('decks', [DeckController::class, 'store']);
Route::get('decks/{deck}', [DeckController::class, 'show']);
Route::put('decks/{deck}', [DeckController::class, 'update']);
Route::delete('decks/{deck}', [DeckController::class, 'destroy']);
Route::get('decks/{deck}/cards', [DeckController::class, 'showByDeck']);
Route::get('decks/{deck}/origins', [DeckController::class, 'getOrigins']);
Route::post('decks/{deck}/add-card', [DeckController::class, 'addCard']);
Route::delete('decks/{deck}/remove-card/{salesCallCard}', [DeckController::class, 'removeCard']);

// Admin - Card Routes
Route::get('cards', [CardController::class, 'index']);
Route::post('cards', [CardController::class, 'store']);
Route::get('cards/{card}', [CardController::class, 'show']);
Route::put('cards/{card}', [CardController::class, 'update']);
Route::delete('cards/{card}', [CardController::class, 'destroy']);

// TODO:
// 1. Market intelligence
// 2. Fix the algorithm
Route::post('generate-cards/{deck}', [CardController::class, 'generate']);

// Admin - Container Routes
// Route::apiResource('containers', ContainerController::class);
Route::get('containers', [ContainerController::class, 'index']);
// Route::post('containers', [ContainerController::class, 'store']);
Route::get('containers/{container}', [ContainerController::class, 'show']);
// Route::put('containers/{container}', [ContainerController::class, 'update']);
// Route::delete('containers/{container}', [ContainerController::class, 'destroy']);

// Basic Room Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('rooms', [RoomController::class, 'index'])->middleware('admin');
    Route::post('rooms', [RoomController::class, 'store'])->middleware('admin');
    Route::get('rooms/{room}', [RoomController::class, 'show']);
    Route::put('rooms/{room}', [RoomController::class, 'update'])->middleware('admin');
    Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->middleware('admin');
});

// Other Room Routes
Route::post('rooms/{room}/join', [RoomController::class, 'joinRoom'])->middleware('auth:sanctum', 'user');
Route::post('rooms/{room}/leave', [RoomController::class, 'leaveRoom'])->middleware('auth:sanctum', 'user');
Route::delete('rooms/{room}/kick/{user}', [RoomController::class, 'kickUser'])->middleware('auth:sanctum', 'admin');
Route::put('rooms/{room}/swap-bays', [RoomController::class, 'swapBays'])->middleware('auth:sanctum', 'admin');
Route::get('rooms/{room}/users', [RoomController::class, 'getRoomUsers'])->middleware('auth:sanctum');
Route::get('rooms/{room}/deck-origins', [RoomController::class, 'getDeckOrigins'])->middleware('auth:sanctum');
Route::put('rooms/{room}/set-ports', [RoomController::class, 'setPorts'])->middleware('auth:sanctum');
Route::put('rooms/{room}/select-deck', [RoomController::class, 'selectDeck'])->middleware('auth:sanctum', 'admin');
Route::post('rooms/{room}/save-config', [RoomController::class, 'saveBayConfig'])->middleware('auth:sanctum', 'admin');
Route::get('rooms/{room}/config', [RoomController::class, 'getBayConfig'])->middleware('auth:sanctum');
Route::get('rooms/{room}/user-port', [RoomController::class, 'getUserPorts'])->middleware('auth:sanctum');
Route::post('rooms/{room}/create-card-temporary/{user}', [RoomController::class, 'createCardTemporary'])->middleware('auth:sanctum', 'admin');
Route::post('card-temporary/accept', [RoomController::class, 'acceptCardTemporary'])->middleware('auth:sanctum');
Route::post('card-temporary/reject', [RoomController::class, 'rejectCardTemporary'])->middleware('auth:sanctum');

// ShipBay Routes
Route::get('ship-bays', [ShipBayController::class, 'index']);
Route::post('ship-bays', [ShipBayController::class, 'store']);
Route::get('ship-bays/{shipBay}', [ShipBayController::class, 'show']);
// Route::put('ship-bays/{shipBay}', [ShipBayController::class, 'update']);
// Route::delete('ship-bays/{shipBay}', [ShipBayController::class, 'destroy']);

// Other ShipBay Routes
Route::get('ship-bays/{room}/{user}', [ShipBayController::class, 'showBayByUserAndRoom']);

// ShipDock Routes
// Route::apiResource('ship-docks', ShipDockController::class);
// Route::get('ship-docks', [ShipDockController::class, 'index']);
Route::post('ship-docks', [ShipDockController::class, 'store']);
// Route::get('ship-docks/{shipDock}', [ShipDockController::class, 'show']);
// Route::put('ship-docks/{shipDock}', [ShipDockController::class, 'update']);
// Route::delete('ship-docks/{shipDock}', [ShipDockController::class, 'destroy']);

// Other ShipDock Routes
Route::get('ship-docks/{room}/{user}', [ShipDockController::class, 'showDockByUserAndRoom']);
